<!DOCTYPE html>
<html class="ng-csp" data-placeholder-focus="false" lang="<?php p($_['language']); ?>" >
	<head data-user="<?php p($_['user_uid']); ?>" data-user-displayname="<?php p($_['user_displayname']); ?>" data-requesttoken="<?php p($_['requesttoken']); ?>">
		<meta charset="utf-8">
		<title>
			<?php
				p(!empty($_['application'])?$_['application'].' - ':'');
				p($theme->getTitle());
			?>
		</title>
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="referrer" content="never">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0">
		<meta name="apple-itunes-app" content="app-id=<?php p($theme->getiTunesAppId()); ?>">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black">
		<meta name="apple-mobile-web-app-title" content="<?php p((!empty($_['application']) && $_['appid']!='files')? $_['application']:'ownCloud'); ?>">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="theme-color" content="<?php p($theme->getMailHeaderColor()); ?>">
		<link rel="icon" href="<?php print_unescaped(image_path($_['appid'], 'favicon.ico')); /* IE11+ supports png */ ?>">
		<link rel="apple-touch-icon-precomposed" href="<?php print_unescaped(image_path($_['appid'], 'favicon-touch.png')); ?>">
		<link rel="mask-icon" sizes="any" href="<?php print_unescaped(image_path($_['appid'], 'favicon-mask.svg')); ?>" color="#1d2d44">
		<link rel="manifest" href="<?php print_unescaped(image_path($_['appid'], 'manifest.json')); ?>">
		<?php foreach($_['cssfiles'] as $cssfile): ?>
			<link rel="stylesheet" href="<?php print_unescaped($cssfile); ?>">
		<?php endforeach; ?>
		<?php foreach($_['printcssfiles'] as $cssfile): ?>
			<link rel="stylesheet" href="<?php print_unescaped($cssfile); ?>" media="print">
		<?php endforeach; ?>
		<?php foreach($_['jsfiles'] as $jsfile): ?>
			<script src="<?php print_unescaped($jsfile); ?>"></script>
		<?php endforeach; ?>
		<?php print_unescaped($_['headers']); ?>
	</head>
	<body id="<?php p($_['bodyid']);?>">
		<?php include('layout.noscript.warning.php'); ?>
		<div id="notification-container">
			<div id="notification"></div>
		</div>
		<header role="banner">
			<header>
				<div class="oc-topbar uk-position-top uk-position-fixed uk-position-z-index" uk-navbar="mode: click">
					<div class="uk-navbar-left">
						<ul class="uk-navbar-nav">
							<li>
								<a href="#">
									<i class="material-icons uk-margin-small-right uk-text-inverse">menu</i>
									<span class="uk-text-inverse"><?php p(!empty($_['application']) ? $_['application'] : $l->t('Apps')); ?></span>
								</a>
								<div class="oc-app-menu" uk-dropdown="animation: uk-animation-slide-top-small; pos: bottom-left; offset: 0; delay-hide:100; mode: click">
									<ul uk-grid class="uk-grid-medium uk-child-width-1-3">
										<?php foreach($_['navigation'] as $entry): ?>
											<li data-id="<?php p($entry['id']); ?>" <?php if( $entry["active"] ): ?> class="_is_active"<?php endif; ?>>
												<!-- <a href="<?php print_unescaped($entry['href']); ?>" tabindex="3" <?php if( $entry['active'] ): ?> class="active"<?php endif; ?>> -->
													<img src="<?php print_unescaped($entry['icon']); ?>">
													<!-- <div class="icon-loading-dark" style="display:none;"></div> -->
													<span>
														<?php p($entry['name']); ?>
													</span>
												<!-- </a> -->
											</li>
										<?php endforeach; ?>
									</div>
								<div>
							</li>
						</ul>
					</div>
					<a href="/" class="uk-position-center uk-width-medium oc-logo-top">
						<img src="/core/img/logo.svg" alt="" style="max-height: 48px;">
					</a>
					<div class="uk-navbar-right">
						<ul class="uk-navbar-nav">
							<li>
								<a href="">
									<i class="material-icons uk-margin-small-right uk-text-inverse">account_circle</i>
									<!-- <img alt="" width="32" height="32" src="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.avatar.getAvatar', ['userId' => $_['user_uid'], 'size' => 32]));?>" srcset="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.avatar.getAvatar', ['userId' => $_['user_uid'], 'size' => 64]));?> 2x, <?php p(\OC::$server->getURLGenerator()->linkToRoute('core.avatar.getAvatar', ['userId' => $_['user_uid'], 'size' => 128]));?> 4x"> -->
									<span class="uk-text-inverse"><?php  p(trim($_['user_displayname']) != '' ? $_['user_displayname'] : $_['user_uid']) ?></span>
								</a>
								<div class="oc-user-menu_" uk-dropdown="animation: uk-animation-slide-top-small; pos: bottom-right; offset: 0; delay-hide:100; mode: click;">
									<ul class="uk-nav uk-navbar-dropdown-nav">
										<?php foreach($_['settingsnavigation'] as $entry):?>
										<li>
											<a href="<?php print_unescaped($entry['href']); ?>">
												<span class="uk-flex uk-flex-middle">
													<i class="material-icons uk-margin-small-right"><?php print_unescaped($entry['icon']); ?></i>
													<?php p($entry['name']) ?>
												</span>
											</a>
										</li>
										<?php endforeach; ?>
										<li>
											<a <?php print_unescaped(OC_User::getLogoutAttribute()); ?>>
												<span class="uk-flex uk-flex-middle">
													<i class="material-icons uk-margin-small-right">exit_to_app</i>
													<?php p($l->t('Log out'));?>
												</span>
											</a>
										</li>
									</ul>
								</div>
							</li>
						</ul>
					</div>
				</div>
			</header>

			<!--
			<ul>
			<?php foreach($_['settingsnavigation'] as $entry):?>
				<li>
					<a href="<?php print_unescaped($entry['href']); ?>"
						<?php if( $entry["active"] ): ?> class="active"<?php endif; ?>>
						<img alt="" src="<?php print_unescaped($entry['icon']); ?>">
						<?php p($entry['name']) ?>
					</a>
				</li>
			<?php endforeach; ?>
				<li>
					<a id="logout" <?php print_unescaped(OC_User::getLogoutAttribute()); ?>>
						<img alt="" src="<?php print_unescaped(image_path('', 'actions/logout.svg')); ?>">
						<?php p($l->t('Log out'));?>
					</a>
				</li>
			</ul>
			-->

			<!--
			<div id="logo-claim" style="display:none;"><?php p($theme->getLogoClaim()); ?></div>
			<a href="<?php print_unescaped(link_to('', 'index.php')); ?>" id="owncloud" tabindex="1">
				<h1 class="logo-icon">
					<?php p($theme->getName()); ?>
				</h1>
			</a>
			-->

			<!-- <div id="header">
				<div id="settings">
					<div id="expand" tabindex="6" role="link" class="menutoggle">
						<?php if ($_['enableAvatars']): ?>
						<div class="avatardiv<?php if ($_['userAvatarSet']) { print_unescaped(' avatardiv-shown'); } else { print_unescaped('" style="display: none'); } ?>">
							<?php if ($_['userAvatarSet']): ?>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<span id="expandDisplayName"></span>
					</div>
					<div id="expanddiv">

					</div>
				</div>

				<form class="searchbox" action="#" method="post" role="search" novalidate>
					<label for="searchbox" class="hidden-visually">
						<?php p($l->t('Search'));?>
					</label>
					<input id="searchbox" type="search" name="query"
						value="" required
						autocomplete="off" tabindex="5">
				</form>
			</div> -->
		</header>

		<nav role="navigation">
			<div id="navigation">
				<div id="apps">
					<ul>
					<?php foreach($_['navigation'] as $entry): ?>
						<li data-id="<?php p($entry['id']); ?>">
							<a href="<?php print_unescaped($entry['href']); ?>" tabindex="3"
								<?php if( $entry['active'] ): ?> class="active"<?php endif; ?>>
								<img class="app-icon" alt="" src="<?php print_unescaped($entry['icon']); ?>">
								<div class="icon-loading-dark" style="display:none;"></div>
								<span>
									<?php p($entry['name']); ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</nav>

		<div id="content-wrapper">
			<div id="content" class="app-<?php p($_['appid']) ?>" role="main">
				<?php print_unescaped($_['content']); ?>
			</div>
		</div>
	</body>
</html>
