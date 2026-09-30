--
-- Give ss_sim the default that simsig_sims.sql declares.
--

ALTER TABLE /*_*/simsig_sims
	MODIFY ss_sim tinyint(1) NOT NULL DEFAULT 0;
