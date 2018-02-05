<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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


namespace Test\User;


use OC\User\BasicAuthModule;
use OC\User\Session;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUser;
use OCP\IUserManager;
use Test\TestCase;

class BasicAuthModuleTest extends TestCase {

	/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $manager;
	/** @var IRequest | \PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var IUser | \PHPUnit_Framework_MockObject_MockObject */
	private $user;
	/** @var Session | \PHPUnit_Framework_MockObject_MockObject */
	private $session;

	public function setUp() {
		parent::setUp();
		$this->manager = $this->createMock(IUserManager::class);
		$this->request = $this->createMock(IRequest::class);
		$this->user = $this->createMock(IUser::class);
		$this->session = $this->createMock(Session::class);

		$this->user->expects($this->any())->method('getUID')->willReturn('user1');

		$this->session->expects($this->any())->method('logClientIn')
			->willReturnMap([
				['user1', '123456', $this->request, true],
				['user2', '123456', $this->request, false],
			]);

		$this->manager->expects($this->any())->method('get')->willReturn($this->user);
	}

	/**
	 * @dataProvider providesCredentials
	 * @param bool $expectsUser
	 * @param string $userId
	 */
	public function testAuth($expectsUser, $userId) {
		$module = new BasicAuthModule($this->manager, $this->session);
		$this->request->server = [
			'PHP_AUTH_USER' => $userId,
			'PHP_AUTH_PW' => '123456',
		];
		if (!$expectsUser) {
			$this->expectException(\Exception::class);
		}
		$this->assertEquals($this->user, $module->auth($this->request));
	}

	public function testGetUserPassword() {

		$s = $this->createMock(ISession::class);
		$s->expects($this->any())->method('exists')->willReturn(false);

		$this->session->expects($this->any())->method('getSession')->willReturn($s);

		// TODO: test app password
		$module = new BasicAuthModule($this->manager, $this->session);
		$this->request->server = [
			'PHP_AUTH_USER' => 'user1',
			'PHP_AUTH_PW' => '123456',
		];
		$this->assertEquals('123456', $module->getUserPassword($this->request));

		$this->request->server = [];
		$this->assertEquals('', $module->getUserPassword($this->request));
	}

	public function providesCredentials() {
		return [
			'user1 can login' => [true, 'user1'],
			'user2 is not known' => [false, 'user2'],
		];
	}
}
