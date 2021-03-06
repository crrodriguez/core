<?php
/**
 * @author Carla Schroder <carla@owncloud.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files\Command;

use OC\Encryption\Manager;
use OC\Files\Filesystem;
use OC\Files\View;
use OCP\Files\FileInfo;
use OCP\Files\Mount\IMountManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TransferOwnership extends Command {

	/** @var IUserManager $userManager */
	private $userManager;

	/** @var IManager */
	private $shareManager;

	/** @var IMountManager */
	private $mountManager;

	/** @var Manager  */
	private $encryptionManager;

	/** @var ILogger  */
	private  $logger;

	/** @var FileInfo[] */
	private $allFiles = [];

	/** @var FileInfo[] */
	private $encryptedFiles = [];

	/** @var IShare[] */
	private $shares = [];

	/** @var string */
	private $sourceUser;

	/** @var string */
	private $destinationUser;

	/** @var string */
	private $inputPath;

	/** @var string */
	private $finalTarget;

	public function __construct(IUserManager $userManager, IManager $shareManager, IMountManager $mountManager, Manager $encryptionManager, ILogger $logger) {
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
		$this->mountManager = $mountManager;
		$this->encryptionManager = $encryptionManager;
		$this->logger = $logger;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('files:transfer-ownership')
			->setDescription('All files and folders are moved to another user - shares are moved as well.')
			->addArgument(
				'source-user',
				InputArgument::REQUIRED,
				'owner of files which shall be moved'
			)
			->addArgument(
				'destination-user',
				InputArgument::REQUIRED,
				'user who will be the new owner of the files'
			)
			->addOption(
				'path',
				null,
				InputOption::VALUE_REQUIRED,
				'selectively provide the path to transfer. For example --path="folder_name"'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$sourceUserObject = $this->userManager->get($input->getArgument('source-user'));
		$destinationUserObject = $this->userManager->get($input->getArgument('destination-user'));
		if (is_null($sourceUserObject)) {
			$output->writeln("<error>Unknown source user $this->sourceUser</error>");
			return 1;
		}
		if (is_null($destinationUserObject)) {
			$output->writeln("<error>Unknown destination user $this->destinationUser</error>");
			return 1;
		}

		$this->sourceUser = $sourceUserObject->getUID();
		$this->destinationUser = $destinationUserObject->getUID();
		$this->inputPath = $input->getOption('path');
		$this->inputPath = ltrim($this->inputPath, '/');

		// target user has to be ready
		if (!\OC::$server->getEncryptionManager()->isReadyForUser($this->destinationUser)) {
			$output->writeln("<error>The target user is not ready to accept files. The user has at least to be logged in once.</error>");
			return 2;
		}

		// use a date format compatible across client OS
		$date = date('Ymd_his');
		$this->finalTarget = "$this->destinationUser/files/transferred from $this->sourceUser on $date";

		// setup filesystem
		Filesystem::initMountPoints($this->sourceUser);
		Filesystem::initMountPoints($this->destinationUser);

		if (strlen($this->inputPath) >= 1) {
			$view = new View();
			$unknownDir = $this->inputPath;
			$this->inputPath = $this->sourceUser . "/files/" . $this->inputPath;
			if (!$view->is_dir($this->inputPath)) {
				$output->writeln("<error>Unknown path provided: $unknownDir</error>");
				return 1;
			}
		}

		// analyse source folder
		$this->analyse($output);

		// collect all the shares
		$this->collectUsersShares($output);

		// transfer the files
		$this->transfer($output);

		// restore the shares
		$this->restoreShares($output);
	}

	private function walkFiles(View $view, $path, \Closure $callBack) {
		foreach ($view->getDirectoryContent($path) as $fileInfo) {
			if (!$callBack($fileInfo)) {
				return;
			}
			if ($fileInfo->getType() === FileInfo::TYPE_FOLDER) {
				$this->walkFiles($view, $fileInfo->getPath(), $callBack);
			}
		}
	}

	/**
	 * @param OutputInterface $output
	 * @throws \Exception
	 */
	protected function analyse(OutputInterface $output) {
		$view = new View();
		$output->writeln("Analysing files of $this->sourceUser ...");
		$progress = new ProgressBar($output);
		$progress->start();
		$self = $this;
		$walkPath = "$this->sourceUser/files";
		if ( strlen($this->inputPath) > 0) {
			if ($this->inputPath !== "$this->sourceUser/files") {
				$walkPath = $this->inputPath;
			}
		}

		$this->walkFiles($view, $walkPath,
				function (FileInfo $fileInfo) use ($progress, $self) {
					if ($fileInfo->getType() === FileInfo::TYPE_FOLDER) {
						// only analyze into folders from main storage,
						// sub-storages have an empty internal path
						if ($fileInfo->getInternalPath() === '' && $fileInfo->getPath() !== '') {
							return false;
						}
						return true;
					}
					$progress->advance();
					$this->allFiles[] = $fileInfo;
					if ($fileInfo->isEncrypted()) {
						if (\OC::$server->getAppConfig()->getValue('encryption', 'useMasterKey', 0) !== 0) {
							/**
							 * We are not going to add this to encryptedFiles array.
							 * Because its encrypted with masterKey and hence it doesn't
							 * require user's specific password.
							 */
							return true;
						}
						$this->encryptedFiles[] = $fileInfo;
					}
					return true;
				});
		$progress->finish();
		$output->writeln('');

		// no file is allowed to be encrypted
		if (!empty($this->encryptedFiles)) {
			$output->writeln("<error>Some files are encrypted - please decrypt them first</error>");
			foreach($this->encryptedFiles as $encryptedFile) {
				/** @var FileInfo $encryptedFile */
				$output->writeln("  " . $encryptedFile->getPath());
			}
			throw new \Exception('Execution terminated.');
		}

	}

	/**
	 * @param OutputInterface $output
	 */
	private function collectUsersShares(OutputInterface $output) {
		$output->writeln("Collecting all share information for files and folder of $this->sourceUser ...");

		$progress = new ProgressBar($output, count($this->shares));
		foreach([\OCP\Share::SHARE_TYPE_GROUP, \OCP\Share::SHARE_TYPE_USER, \OCP\Share::SHARE_TYPE_LINK, \OCP\Share::SHARE_TYPE_REMOTE] as $shareType) {
		$offset = 0;
			while (true) {
				$sharePage = $this->shareManager->getSharesBy($this->sourceUser, $shareType, null, true, 50, $offset);
				$progress->advance(count($sharePage));
				if (empty($sharePage)) {
					break;
				}
				$this->shares = array_merge($this->shares, $sharePage);
				$offset += 50;
			}
		}

		$progress->finish();
		$output->writeln('');
	}

	/**
	 * @param OutputInterface $output
	 */
	protected function transfer(OutputInterface $output) {
		$view = new View();
		$output->writeln("Transferring files to $this->finalTarget ...");
		$sourcePath = (strlen($this->inputPath) > 0) ? $this->inputPath : "$this->sourceUser/files";
		// This change will help user to transfer the folder specified using --path option.
		// Else only the content inside folder is transferred which is not correct.
		if (strlen($this->inputPath) > 0) {
			if($this->inputPath !== ltrim("$this->sourceUser/files", '/')) {
				$view->mkdir($this->finalTarget);
				$this->finalTarget = $this->finalTarget . '/' . basename($sourcePath);
			}
		}

		$view->rename($sourcePath, $this->finalTarget);

		if (!is_dir("$this->sourceUser/files")) {
			// because the files folder is moved away we need to recreate it
			$view->mkdir("$this->sourceUser/files");
		}
	}

	/**
	 * @param OutputInterface $output
	 */
	private function restoreShares(OutputInterface $output) {
		$output->writeln("Restoring shares ...");
		$progress = new ProgressBar($output, count($this->shares));

		foreach($this->shares as $share) {
			try {
				if ($share->getSharedWith() === $this->destinationUser) {
					// Unmount the shares before deleting, so we don't try to get the storage later on.
					$shareMountPoint = $this->mountManager->find('/' . $this->destinationUser . '/files' . $share->getTarget());
					if ($shareMountPoint) {
						$this->mountManager->removeMount($shareMountPoint->getMountPoint());
					}
					$this->shareManager->deleteShare($share);
				} else {
					if ($share->getShareOwner() === $this->sourceUser) {
						$share->setShareOwner($this->destinationUser);
					}
					if ($share->getSharedBy() === $this->sourceUser) {
						$share->setSharedBy($this->destinationUser);
					}
					/*
					 * If the share is already moved then updateShare would cause exception
					 * This can happen if the folder is shared and file(s) inside the folder
					 * has shares, for example public link
					 */
					if ($share->getShareType() === \OCP\Share::SHARE_TYPE_LINK) {
						$sharePath = ltrim($share->getNode()->getPath(), '/');
						if (strpos($sharePath, $this->finalTarget) !== false) {
							//The share is already moved
							continue;
						}
					}

					$this->shareManager->updateShare($share);
				}
			} catch (\OCP\Files\NotFoundException $e) {
				$output->writeln('<error>Share with id ' . $share->getId() . ' points at deleted file, skipping</error>');
			} catch (\Exception $e) {
				$output->writeln('<error>Could not restore share with id ' . $share->getId() . ':' . $e->getTraceAsString() . '</error>');
			}
			$progress->advance();
		}
		$progress->finish();
		$output->writeln('');
	}
}
