-- Store QuoteAuthorization stamps so they are publicly dereferenceable (FEP-044f)
CREATE TABLE IF NOT EXISTS quote_authorizations (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id          INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    stamp_uuid       TEXT    NOT NULL,
    quoting_post_uri TEXT    NOT NULL,
    created_at       TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(post_id, quoting_post_uri)
);

CREATE INDEX IF NOT EXISTS idx_qa_post_id ON quote_authorizations(post_id);
