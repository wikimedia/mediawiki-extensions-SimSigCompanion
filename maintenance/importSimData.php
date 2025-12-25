<?php

namespace MediaWiki\Extension\SimSigCompanion\Maintenance;

use Maintenance;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../';
}
require_once "$IP/maintenance/Maintenance.php";

class ImportSimData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Scan the data directory and import SimSig simulations into the database.' );
	}

	public function execute() {
		$dataDir = __DIR__ . '/../data';
		if ( !is_dir( $dataDir ) ) {
			$this->fatalError( "Data directory not found: $dataDir" );
		}

		$files = glob( $dataDir . '/*.json' );
		if ( !$files ) {
			$this->output( "No JSON files found in $dataDir\n" );
			return;
		}

		$dbw = $this->getDB( DB_PRIMARY );
		$count = 0;

		foreach ( $files as $file ) {
			$filename = basename( $file, '.json' );
			$content = file_get_contents( $file );
			$data = json_decode( $content, true );

			if ( !isset( $data['name'] ) ) {
				$this->output( "Skipping $file: 'name' field not found.\n" );
				continue;
			}

			$dbw->upsert(
				'simsig_sims',
				[
					'ss_filename' => $filename,
					'ss_name' => $data['name'],
					'ss_sim' => (bool)( $data['sim'] ?? false ),
				],
				[ 'ss_filename' ],
				[
					'ss_name' => $data['name'],
					'ss_sim' => (bool)( $data['sim'] ?? false ),
				],
				__METHOD__
			);

			$count++;
		}

		$this->output( "Imported $count simulations.\n" );
	}
}

$maintClass = ImportSimData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
