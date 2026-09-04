INSERT INTO review_forms (review_form_id, assoc_type, assoc_id, seq, is_active)
VALUES (1, 256, 1, 1, 1);
INSERT INTO review_form_settings
    (review_form_id, locale, setting_name, setting_value, setting_type)
VALUES
    (1, 'en', 'title', 'Reference Review Form', 'string'),
    (1, 'en', 'description', 'Synthetic review form for round trip coverage', 'string');
INSERT INTO review_form_elements
    (review_form_element_id, review_form_id, seq, element_type, required, included)
VALUES (1, 1, 1, 1, 1, 1);
INSERT INTO review_form_element_settings
    (review_form_element_id, locale, setting_name, setting_value, setting_type)
VALUES
    (1, 'en', 'question', 'Does the submission meet the review criteria?', 'string'),
    (1, 'en', 'description', 'Provide a concise synthetic response', 'string');
UPDATE sections SET review_form_id = 1 WHERE section_id = 1 AND journal_id = 1;
UPDATE review_assignments SET review_form_id = 1 WHERE review_id = 10 AND submission_id = 7;
INSERT INTO review_form_responses
    (review_form_element_id, review_id, response_type, response_value)
VALUES (1, 10, 'string', 'The submission meets the reference criteria.');
INSERT INTO review_rounds
    (review_round_id, submission_id, stage_id, round, status)
VALUES (14, 1, 3, 2, 2);
INSERT INTO review_assignments
    (review_id, submission_id, reviewer_id, reminder_was_automatic, declined, cancelled,
     review_round_id, stage_id, review_method, round, step, request_resent)
VALUES (28, 1, 7, 0, 0, 0, 14, 3, 2, 2, 1, 0);

INSERT INTO issue_files
    (file_id, issue_id, file_name, file_type, file_size, content_type, original_file_name, date_uploaded, date_modified)
VALUES
    (1, 1, 'reference-issue.pdf', 'application/pdf', 77, 1, 'reference-issue.pdf',
     '2024-01-15 12:00:00', '2024-01-15 12:00:00');
INSERT INTO issue_galleys
    (galley_id, locale, issue_id, file_id, label, seq, url_path)
VALUES (1, 'en', 1, 1, 'PDF', 1, 'reference-issue');

INSERT INTO files (file_id, path, mimetype)
VALUES (27, 'journals/1/articles/2/reference-discussion.txt', 'text/plain');
INSERT INTO submission_files
    (submission_file_id, submission_id, file_id, source_submission_file_id, genre_id, file_stage,
     direct_sales_price, sales_type, viewable, created_at, updated_at, uploader_user_id, assoc_type,
     assoc_id)
VALUES
    (44, 2, 27, NULL, 12, 3, NULL, NULL, NULL, '2024-01-15 12:00:00',
     '2024-01-15 12:00:00', 6, 520, 1);
INSERT INTO submission_file_revisions (revision_id, submission_file_id, file_id)
VALUES (44, 44, 27);
INSERT INTO submission_file_settings
    (submission_file_setting_id, submission_file_id, locale, setting_name, setting_value)
VALUES (46, 44, 'en', 'name', 'Reference discussion attachment');

INSERT INTO metrics_context
    (metrics_context_id, load_id, context_id, date, metric)
VALUES (1, 'reference-20240115.log', 1, '2024-01-15', 7);
INSERT INTO metrics_submission
    (metrics_submission_id, load_id, context_id, submission_id, representation_id, submission_file_id,
     file_type, assoc_type, date, metric)
VALUES (1, 'reference-20240115.log', 1, 1, NULL, NULL, NULL, 1048585, '2024-01-15', 11);
INSERT INTO metrics_issue
    (metrics_issue_id, load_id, context_id, issue_id, issue_galley_id, date, metric)
VALUES (1, 'reference-20240115.log', 1, 1, 1, '2024-01-15', 13);
INSERT INTO metrics_submission_geo_daily
    (metrics_submission_geo_daily_id, load_id, context_id, submission_id, country, region, city, date,
     metric, metric_unique)
VALUES (1, 'reference-20240115.log', 1, 1, 'BR', 'AM', 'Manaus', '2024-01-15', 5, 3);
INSERT INTO metrics_submission_geo_monthly
    (metrics_submission_geo_monthly_id, context_id, submission_id, country, region, city, month,
     metric, metric_unique)
VALUES (1, 1, 1, 'BR', 'AM', 'Manaus', 202401, 5, 3);
INSERT INTO metrics_counter_submission_daily
    (metrics_counter_submission_daily_id, load_id, context_id, submission_id, date,
     metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
VALUES (1, 'reference-20240115.log', 1, 1, '2024-01-15', 7, 4, 5, 2);
INSERT INTO metrics_counter_submission_monthly
    (metrics_counter_submission_monthly_id, context_id, submission_id, month,
     metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
VALUES (1, 1, 1, 202401, 7, 4, 5, 2);
INSERT INTO institutions (institution_id, context_id, ror, deleted_at)
VALUES (1, 1, 'https://ror.org/01abcde12', NULL);
INSERT INTO metrics_counter_submission_institution_daily
    (metrics_counter_submission_institution_daily_id, load_id, context_id, submission_id,
     institution_id, date, metric_investigations, metric_investigations_unique, metric_requests,
     metric_requests_unique)
VALUES (1, 'reference-20240115.log', 1, 1, 1, '2024-01-15', 9, 4, 6, 3);
INSERT INTO metrics_counter_submission_institution_monthly
    (metrics_counter_submission_institution_monthly_id, context_id, submission_id, institution_id,
     month, metric_investigations, metric_investigations_unique, metric_requests,
     metric_requests_unique)
VALUES (1, 1, 1, 1, 202401, 9, 4, 6, 3);

UPDATE users SET
    username = CONCAT('reference', user_id),
    password = '$2y$10$G0/6At7QXMySl5ZzDEIhAuIkcqzBktowO0UPdJXQVWGSUzxzAu4ma',
    email = CONCAT('reference-', user_id, '@example.test'),
    url = NULL,
    phone = NULL,
    mailing_address = NULL,
    billing_address = NULL,
    country = NULL,
    gossip = NULL,
    auth_id = NULL,
    auth_str = NULL,
    disabled_reason = NULL;
UPDATE user_settings SET setting_value = CASE setting_name
    WHEN 'givenName' THEN CONCAT('Reference ', user_id)
    WHEN 'familyName' THEN CONCAT('User ', user_id)
    WHEN 'affiliation' THEN 'Synthetic Research Institute'
    WHEN 'biography' THEN ''
    WHEN 'preferredPublicName' THEN ''
    WHEN 'signature' THEN ''
    WHEN 'orcid' THEN ''
    ELSE setting_value
END;
UPDATE authors SET email = CONCAT('author-', author_id, '@example.test');
UPDATE author_settings SET setting_value = CASE setting_name
    WHEN 'givenName' THEN CONCAT('Reference ', author_id)
    WHEN 'familyName' THEN CONCAT('Author ', author_id)
    WHEN 'affiliation' THEN 'Synthetic Research Institute'
    WHEN 'biography' THEN ''
    WHEN 'preferredPublicName' THEN ''
    WHEN 'country' THEN ''
    ELSE setting_value
END;
UPDATE journals SET path = 'reference-journal' WHERE journal_id = 1;
UPDATE journal_settings SET setting_value = CASE setting_name
    WHEN 'name' THEN 'Reference Journal'
    WHEN 'acronym' THEN 'RJ'
    WHEN 'description' THEN 'Synthetic journal for round trip coverage.'
    WHEN 'contactEmail' THEN 'journal@example.test'
    WHEN 'supportEmail' THEN 'support@example.test'
    WHEN 'contactName' THEN 'Reference Contact'
    WHEN 'supportName' THEN 'Reference Support'
    WHEN 'mailingAddress' THEN 'Synthetic address'
    WHEN 'publisherInstitution' THEN 'Synthetic Publisher'
    ELSE setting_value
END;
UPDATE journal_settings SET setting_value = REPLACE(
    REPLACE(
        REPLACE(setting_value, 'Journal of Public Knowledge', 'Reference Journal'),
        'Journal de la connaissance du public',
        'Reference Journal'
    ),
    'publicknowledge',
    'reference-journal'
);
UPDATE site_settings SET setting_value = CASE setting_name
    WHEN 'contactEmail' THEN 'site@example.test'
    WHEN 'contactName' THEN 'Reference Site'
    ELSE setting_value
END;
UPDATE publication_settings SET setting_value = CASE setting_name
    WHEN 'title' THEN CONCAT('Reference Submission ', publication_id)
    WHEN 'subtitle' THEN ''
    WHEN 'abstract' THEN 'Synthetic abstract for round trip coverage.'
    WHEN 'citationsRaw' THEN ''
    ELSE setting_value
END;
UPDATE publications SET url_path = CONCAT('reference-', publication_id) WHERE url_path IS NOT NULL;
UPDATE submission_comments SET
    comment_title = 'Synthetic review comment',
    comments = 'Synthetic review comment for round trip coverage.';
UPDATE notes SET
    title = 'Synthetic editorial note',
    contents = 'Synthetic editorial note for round trip coverage.';
DELETE FROM access_keys;
DELETE FROM email_log;
DELETE FROM event_log;
DELETE FROM notifications;
DELETE FROM sessions;
DELETE FROM jobs;
DELETE FROM failed_jobs;
DELETE FROM temporary_files;
DELETE FROM submission_search_object_keywords;
DELETE FROM submission_search_objects;
DELETE FROM submission_search_keyword_list;
