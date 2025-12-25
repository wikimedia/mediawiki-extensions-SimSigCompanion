-- SQL for SimSigCompanion extension
CREATE TABLE IF NOT EXISTS /*_*/simsig_sims (
    ss_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
    ss_filename varchar(255) NOT NULL,
    ss_name varchar(255) NOT NULL,
    ss_sim tinyint(1) NOT NULL DEFAULT 0
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX IF NOT EXISTS /*_*/ss_filename ON /*_*/simsig_sims (ss_filename);

CREATE TABLE IF NOT EXISTS /*_*/simsig_ownership (
    ss_owner_id int unsigned NOT NULL,
    ss_sim_id int unsigned NOT NULL,
    PRIMARY KEY (ss_owner_id, ss_sim_id)
) /*$wgDBTableOptions*/;
