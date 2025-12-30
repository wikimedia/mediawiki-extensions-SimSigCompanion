--
-- Table structure for table `simsig_ownership`
--

CREATE TABLE IF NOT EXISTS /*_*/simsig_ownership (
	ss_owner_id int unsigned NOT NULL,
	ss_sim_id int unsigned NOT NULL,
	PRIMARY KEY (ss_owner_id, ss_sim_id)
) /*$wgDBTableOptions*/;
