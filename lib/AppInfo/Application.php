<?php

/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Recognize\AppInfo;

use OCA\DAV\Connector\Sabre\Principal;
use OCA\Recognize\Hooks\FileListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'recognize';

	public function __construct() {
		parent::__construct(self::APP_ID);
		/**
		 * @var IEventDispatcher $dispatcher
		 */
		$dispatcher = $this->getContainer()->get(IEventDispatcher::class);
		$dispatcher->addServiceListener(BeforeNodeDeletedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(NodeDeletedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(NodeCreatedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(NodeRenamedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(BeforeNodeRenamedEvent::class, FileListener::class);
	}

	public function register(IRegistrationContext $context): void {
		@include_once __DIR__ . '/../../vendor/autoload.php';

		// Load Rubix functions because Mozart doesn't pick up on them
		// see https://github.com/coenjacobs/mozart/issues/66
		require_once(__DIR__.'/../Vendor/Rubix/ML/functions.php');
		require_once(__DIR__.'/../Vendor/Rubix/ML/constants.php');

		/** Register $principalBackend for the DAV collection */
		$context->registerServiceAlias('principalBackend', Principal::class);
	}

	/**
	 * @throws \Throwable
	 */
	public function boot(IBootContext $context): void {
	}
}
