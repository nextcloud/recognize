<?php

/*
 * Copyright (c) 2021-2022 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\AppInfo;

use OCA\DAV\Connector\Sabre\Principal;
use OCA\Recognize\Hooks\FileListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Cache\CacheEntryInsertedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\NodeRemovedFromCache;
use OCP\Share\Events\ShareAcceptedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;

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
		$dispatcher->addServiceListener(ShareCreatedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(ShareAcceptedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(ShareDeletedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(CacheEntryInsertedEvent::class, FileListener::class);
		$dispatcher->addServiceListener(NodeRemovedFromCache::class, FileListener::class);
	}

	public function register(IRegistrationContext $context): void {
		@include_once __DIR__ . '/../../vendor/scoper-autoload.php';
		@include_once __DIR__ . '/../../vendor/rubix/ml/src/functions.php';
		@include_once __DIR__ . '/../../vendor/rubix/ml/src/constants.php';

		/** Register $principalBackend for the DAV collection */
		$context->registerServiceAlias('principalBackend', Principal::class);
	}

	/**
	 * @throws \Throwable
	 */
	public function boot(IBootContext $context): void {
	}
}
