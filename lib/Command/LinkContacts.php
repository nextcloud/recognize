<?php

/*
 * Copyright (c) 2026 The Recognize contributors.
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */
declare(strict_types=1);
namespace OCA\Recognize\Command;

use OCA\Recognize\Db\FaceClusterMapper;
use OCA\Recognize\Service\ContactLinkService;
use OCA\Recognize\Service\ContactPhotoAnalyzer;
use OCA\Recognize\Service\Logger;
use OCP\DB\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class LinkContacts extends Command {
	public function __construct(
		private Logger $logger,
		private FaceClusterMapper $clusterMapper,
		private ContactLinkService $contactLinkService,
		private ContactPhotoAnalyzer $analyzer,
	) {
		parent::__construct();
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:link-contacts')
			->setDescription('Link face clusters to contacts by matching the photos of the contacts against the clusters')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'Only link the contacts of this user');
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->logger->setCliOutput($output);

		if (!$this->analyzer->isAvailable()) {
			$output->writeln('<error>Contact photos cannot be analyzed: ' . $this->analyzer->getUnavailableReason() . '</error>');
			return 1;
		}

		/** @var string|null $user */
		$user = $input->getOption('user');
		if (is_string($user) && $user !== '') {
			$userIds = [$user];
		} else {
			try {
				$userIds = $this->clusterMapper->findUserIds();
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				return 1;
			}
		}

		foreach ($userIds as $userId) {
			$this->logger->info('Linking face clusters of user ' . $userId . ' to contacts');
			try {
				$report = $this->contactLinkService->linkContactsForUser($userId);
				$output->writeln($userId . ': ' . $report['linked'] . ' linked, ' . $report['analyzed'] . ' contact photos analyzed, ' . $report['ambiguous'] . ' ambiguous');
			} catch (\Throwable $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
		return 0;
	}
}
