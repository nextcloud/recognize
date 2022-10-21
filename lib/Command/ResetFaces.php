<?php

namespace OCA\Recognize\Command;

use OCA\Recognize\Db\FaceDetectionMapper;
use OCA\Recognize\Service\TagManager;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetFaces extends Command {
	private IDBConnection $db;

	public function __construct(IDBConnection $db) {
		parent::__construct();
		$this->db = $db;
	}

	/**
	 * Configure the command
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setName('recognize:reset-faces')
			->setDescription('Remove all face detections from previously classified files');
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
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('recognize_face_detections')
				->executeStatement();
			$qb = $this->db->getQueryBuilder();
			$qb->delete('recognize_face_clusters')
				->executeStatement();
		} catch (\Exception $ex) {
			$output->writeln('<error>Failed to reset faces</error>');
			$output->writeln($ex->getMessage());
			return 1;
		}

		return 0;
	}
}
