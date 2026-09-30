<?php

namespace MediaWiki\Extension\SimSigCompanion;

use MediaWiki\Installer\DatabaseUpdater;

class Hooks {

	/**
	 * Apply the default schema for database tables and indexes.
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return void
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		// Create basic schema
		$updater->addExtensionTable(
			'simsig_sims',
			__DIR__ . '/../sql/simsig_sims.sql'
		);
		$updater->addExtensionTable(
			'simsig_ownership',
			__DIR__ . '/../sql/simsig_ownership.sql'
		);

		// Installs that were undertaken pre-the addition of defaults
		// i.e testing sites run into issues (re-) inserting simulation
		// data due to it currently being a manually constructed column
		$updater->modifyExtensionField(
			'simsig_sims',
			'ss_sim',
			__DIR__ . '/../sql/patch-simsig_sims-ss_sim-default.sql'
		);
		$updater->modifyExtensionField(
			'simsig_sims',
			'ss_free',
			__DIR__ . '/../sql/patch-simsig_sims-ss_free-default.sql'
		);

		$updater->addExtensionIndex(
			'simsig_sims',
			'ss_filename',
			__DIR__ . '/../sql/index-simsig_ss_filename.sql'
		);
	}
}
