<?php

namespace MediaWiki\Extension\SimSigCompanion;

use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;

class SpecialMySimSig extends \SpecialPage {

	private TemplateParser $templateParser;

	public function __construct() {
		parent::__construct( 'MySimSig' );

		$this->templateParser = new TemplateParser( __DIR__ . '/../templates' );
	}

	/**
	 * @param string $par
	 *
	 * @return void
	 */
	public function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$user = $this->getUser();

		$this->setHeaders();

		$output->addModuleStyles( 'ext.simsigcompanion.mysimsig.styles' );

		// Handle form submission
		if ( $request->wasPosted() &&
			$this->getContext()->getCsrfTokenSet()->matchToken( $request->getVal( 'wpEditToken' ) ) ) {
			$this->handleFormSubmission( $request, $user );
		}

		// Display the form
		$this->displaySimTable( $output, $user );
	}

	/**
	 * Get data from our tables and combine it, passing all relevant data to our Mustache template.
	 *
	 * @param OutputPage $output
	 * @param User $user
	 *
	 * @return void
	 */
	private function displaySimTable( $output, $user ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$userId = $user->getId();

		$data = $dbr->newSelectQueryBuilder()
			->table( 'simsig_sims' )
			->select( '*' )
			->where( 'ss_sim = 1' )
			->orderBy( 'ss_name' )
			->caller( __METHOD__ )->fetchResultSet();

		$simOwnershipData = [];
		foreach ( $data as $row ) {
			$simOwnershipData[$row->ss_id] = [
				'simId' => $row->ss_id,
				'simName' => $row->ss_name,
				'simFree' => (bool)$row->ss_free,
				'simOwned' => false,
			];
		}

		// Get current user's ownership records
		if ( $userId ) {
			$ownedRows = $dbr->newSelectQueryBuilder()
				->table( 'simsig_ownership' )
				->select( 'ss_sim_id' )
				->where( [ 'ss_owner_id' => $userId ] )
				->caller( __METHOD__ )->fetchResultSet();

			foreach ( $ownedRows as $row ) {
				$simOwnershipData[$row->ss_sim_id]['simOwned'] = true;
			}
		}

		$myssdata = [
			'mysimsig-header' => $this->msg( 'mysimsig-pageheader' )->text(),
			'mysimsig-formURL' => $this->getPageTitle()->getLocalURL(),
			'mysimsig-table-header-simulations' => $this->msg( 'mysimsig-table-header-simulations' )->text(),
			'mysimsig-table-header-owned' => $this->msg( 'mysimsig-table-header-owned' )->text(),
			'mysimsig-data' => array_values( $simOwnershipData ),
			'mysimsig-edittoken' => $user->getEditToken(),
			'mysimsig-button-submit' => $this->msg( 'mysimsig-form-submit' )->text(),
			'mysimsig-error-nosims' => $this->msg( 'mysimsig-error-nosims' )->text()
		];

		// Mustache/Codex template
		$spTemplate = $this->templateParser->processTemplate(
			'MySimSigPage',
			$myssdata,
		);
		$output->addHTML( $spTemplate );
	}

	/**
	 * Take form input and update the data back to the database accordingly.
	 * Displays an interface message on the page if successful.
	 *
	 * @param WebRequest $request
	 * @param User $user
	 *
	 * @return void
	 */
	private function handleFormSubmission( $request, $user ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$userId = $user->getId();

		// Get all sims
		$sims = $dbw->newSelectQueryBuilder()
			->table( 'simsig_sims' )
			->fields( [ 'ss_id' ] )
			->where( [ 'ss_sim' => true ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Delete existing ownership records for this user
		$dbw->newDeleteQueryBuilder()
			->table( 'simsig_ownership' )
			->where( [ 'ss_owner_id' => $userId ] )
			->caller( __METHOD__ )
			->execute();

		// Insert new ownership records based on checked boxes
		$toInsert = [];
		foreach ( $sims as $sim ) {
			$simId = $sim->ss_id;
			if ( $request->getBool( 'owned_' . $simId ) ) {
				$toInsert[] = [
					'ss_owner_id' => $userId,
					'ss_sim_id' => $simId
				];
			}
		}

		if ( $toInsert ) {
			$dbw->insert(
				'simsig_ownership',
				$toInsert,
				__METHOD__
			);
		}

		$this->getOutput()->msg( 'mysimsig-updated' );
	}
}
