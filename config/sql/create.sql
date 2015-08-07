CREATE DATABASE sgoc;

\c sgoc;

CREATE TABLE sg_pull (
id  bigserial NOT NULL,
survey_id bigint NOT NULL,
last_pull_ts timestamp NOT NULL,
PRIMARY KEY (id),
UNIQUE (survey_id)
);