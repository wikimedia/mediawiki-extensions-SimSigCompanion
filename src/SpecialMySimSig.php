<?php

namespace MediaWiki\Extension\SimSigCompanion;

use MediaWiki\MediaWikiServices;
use MediaWiki\Html\Html;
use MediaWiki\Session\CsrfTokenSet;

class SpecialMySimSig extends \SpecialPage {
	function __construct() {
		parent::__construct( 'MySimSig' );
	}

	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$user = $this->getUser();

		$this->setHeaders();

		// Handle form submission
		if ( $request->wasPosted() &&
			$this->getContext()->getCsrfTokenSet()->matchToken( $request->getVal( 'wpEditToken' ) ) ) {
			$this->handleFormSubmission( $request, $user );
		}

		// Display the form
		$this->displaySimTable( $output, $user );
	}

	private function displaySimTable( $output, $user ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );
		$userId = $user->getId();

		// Get all sims where ss_sim is true
		$sims = $dbr->select(
			'simsig_sims',
			[ 'ss_id', 'ss_name' ],
			[ 'ss_sim' => true ],
			__METHOD__,
			[ 'ORDER BY' => 'ss_name' ]
		);

		// Get current user's ownership records
		$owned = [];
		if ( $userId ) {
			$ownedRows = $dbr->select(
				'simsig_ownership',
				[ 'ss_sim_id' ],
				[ 'ss_owner_id' => $userId ],
				__METHOD__
			);

			foreach ( $ownedRows as $row ) {
				$owned[$row->ss_sim_id] = true;
			}
		}

		// Build the form
		$html = Html::openElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL()
		] );

		// Add table
		$html .= Html::openElement( 'table', [ 'class' => 'wikitable sortable' ] );
		$html .= Html::openElement( 'thead' );
		$html .= Html::openElement( 'tr' );
		$html .= Html::element( 'th', [], 'Simulation Name' );
		$html .= Html::element( 'th', [], 'Owned' );
		$html .= Html::closeElement( 'tr' );
		$html .= Html::closeElement( 'thead' );

		$html .= Html::openElement( 'tbody' );

		foreach ( $sims as $sim ) {
			$simId = $sim->ss_id;
			$simName = $sim->ss_name ?? 'Unknown Sim';
			$isOwned = isset( $owned[$simId] );

			$html .= Html::openElement( 'tr' );
			$html .= Html::rawElement( 'td', [], htmlspecialchars( $simName ) );
			$html .= Html::rawElement( 'td', [],
				Html::check( 'owned_' . $simId, $isOwned, [ 'value' => '1' ] )
			);
			$html .= Html::closeElement( 'tr' );
		}

		$html .= Html::closeElement( 'tbody' );
		$html .= Html::closeElement( 'table' );

		// Add submit button and token
		$html .= Html::hidden( 'wpEditToken', $user->getEditToken() );
		$html .= Html::submitButton( 'Save Changes', [ 'name' => 'wpSave' ] );

		$html .= Html::closeElement( 'form' );

		$output->addHTML( $html );
	}

	private function handleFormSubmission( $request, $user ) {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		$userId = $user->getId();

		// Get all sims
		$sims = $dbw->select(
			'simsig_sims',
			[ 'ss_id' ],
			[ 'ss_sim' => true ],
			__METHOD__
		);

		// Delete existing ownership records for this user
		$dbw->delete(
			'simsig_ownership',
			[ 'ss_owner_id' => $userId ],
			__METHOD__
		);

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

		$this->getOutput()->msg('mysimsig-updated' );
	}
}
