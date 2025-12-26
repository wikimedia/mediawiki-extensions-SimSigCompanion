<?php

namespace MediaWiki\Extension\SimSigCompanion;

use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

class SpecialSimSig extends \SpecialPage {

	private TemplateParser $templateParser;

	public function __construct() {
		parent::__construct( 'SimSig' );
	}

	/**
	 * @param $par
	 *
	 * @return void
	 */
	public function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();

		$this->templateParser = new TemplateParser( __DIR__ . '/../templates' );

		$this->renderSims();
	}

	/**
	 * Fetch data from the database and combine it into an array suitable to iterated in Mustache.
	 *
	 * @return array
	 */
	private function getSimOwnershipData() {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();

		$paidSims = [];

		$sims = $db->newSelectQueryBuilder()
			->select( [ 'ss_name', 'ss_id' ] )->distinct()
			->table( 'simsig_sims' )
			->where( [ 'ss_sim' => true, 'ss_free' => false ] )
			->caller( __METHOD__ )->fetchResultSet();

		foreach ( $sims as $sim ) {
			$ownership = $db->newSelectQueryBuilder()
				->table( 'simsig_ownership' )
				->select( [ 'ss_owner_id' ] )
				->where( [ 'ss_sim_id' => $sim->ss_id ] )
				->caller( __METHOD__ )->fetchResultSet();

			$owners = [];
			foreach ( $ownership as $owner ) {
				$owner = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $owner->ss_owner_id );
				$owners[] = $owner->getName();
			}
			$paidSims[] = [ 'sim' => $sim->ss_name, 'owners' => $owners ];
		}

		return $paidSims;
	}

	/**
	 * Free/donationware simulations are displayed differently, as everyone "owns" them and this would otherwise flood
	 * the table, so we don't need to combine data here.
	 *
	 * @return array
	 */
	private function getFreeSims() {
		$db = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()->getReplicaDatabase();
		$freeSims = $db->newSelectQueryBuilder()
			->select( [ 'ss_name' ] )->distinct()
			->table( 'simsig_sims' )
			->where( [ 'ss_sim' => true, 'ss_free' => true ] )
			->caller( __METHOD__ )->fetchResultSet();

		$freeSimNames = [];
		foreach ( $freeSims as $sim ) {
			$freeSimNames[] = $sim->ss_name;
		}
		return $freeSimNames;
	}

	/**
	 * Pass everything we need to Mustache.
	 *
	 * @return void
	 */
	private function renderSims() {
		$output = $this->getOutput();

		$output->addModuleStyles( 'ext.simsigcompanion.mysimsig.styles' );

		$communityData = [
			'simsigcomm-data' => $this->getSimOwnershipData(),
			'simsigcomm-table-header-simulations' => $this->msg( 'simsigcomm-table-header-simulations' )->text(),
			'simsigcomm-table-header-owners' => $this->msg( 'simsigcomm-table-header-owners' )->text(),
			'simsigcomm-error-nosims' => $this->msg( 'simsigcomm-error-nosims' )->text(),
			'simsigcomm-msg-noowners' => $this->msg( 'simsigcomm-message-noowners' )->text(),
			'simsigcomm-header' => $this->msg( 'simsigcomm-pageheader' )->parse(),
			'simsigcomm-table-free-sims' => $this->msg( 'simsigcomm-message-freesims' )->params(
				$this->getLanguage()->commaList( $this->getFreeSims() )	)->text(),
		];

		$spTemplate = $this->templateParser->processTemplate(
			'SimSigCommunity',
			$communityData
		);
		$output->addHTML( $spTemplate );
	}
}
