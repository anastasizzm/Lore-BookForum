-- =====================================================================
-- Lore platform schema
-- Requires PostgreSQL 18+ (uuidv7())
-- =====================================================================

BEGIN;

CREATE TABLE users (
    id          BIGSERIAL PRIMARY KEY,
    username    VARCHAR(30)  NOT NULL CHECK (username ~ '^[a-zA-Z0-9_]{3,30}$'),
    email       VARCHAR(255) NOT NULL,
    pass_hash   TEXT         NOT NULL,
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX uq_users_username_lower ON users (lower(username));
CREATE UNIQUE INDEX uq_users_email_lower    ON users (lower(email));


CREATE TABLE users_rules (
    id           BIGSERIAL PRIMARY KEY,
    user_id      BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    is_redactor  BOOLEAN NOT NULL DEFAULT FALSE,
    is_admin     BOOLEAN NOT NULL DEFAULT FALSE
);


CREATE TABLE files (
    id            UUID PRIMARY KEY DEFAULT uuidv7(),
    object_key    TEXT        NOT NULL UNIQUE,
    content_type  TEXT        NOT NULL,
    size_bytes    BIGINT      NOT NULL CHECK (size_bytes >= 0),
    width         INT         CHECK (width  > 0),
    height        INT         CHECK (height > 0),
    uploaded_by   BIGINT      REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    is_active     BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT files_dimensions_both_or_none
        CHECK ((width IS NULL) = (height IS NULL))
);

CREATE INDEX idx_files_uploaded_by ON files (uploaded_by) WHERE uploaded_by IS NOT NULL;


CREATE TABLE profiles (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT       NOT NULL UNIQUE REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    name        VARCHAR(255) NOT NULL,
    surname     VARCHAR(255) NOT NULL,
    bio         TEXT NOT NULL DEFAULT '',
    icon_id     UUID         NOT NULL REFERENCES files (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_profiles_icon_id ON profiles (icon_id);


CREATE TABLE genres (
    id     SERIAL PRIMARY KEY,
    title  VARCHAR(255) NOT NULL UNIQUE
);

CREATE TABLE categories (
    id     SERIAL PRIMARY KEY,
    title  VARCHAR(255) NOT NULL UNIQUE
);


CREATE TABLE publications (
    id            BIGSERIAL PRIMARY KEY,
    title         VARCHAR(255)  NOT NULL,
    description   VARCHAR(4000) NOT NULL,
    genre_id      INT           NOT NULL REFERENCES genres (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    author_notes  VARCHAR(2000) NOT NULL,
    icon_id       UUID          REFERENCES files (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    creator_id    BIGINT        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    created_at    TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_publications_created_at     ON publications (created_at DESC);
CREATE INDEX idx_publications_genre_created  ON publications (genre_id, created_at DESC);
CREATE INDEX idx_publications_icon_id        ON publications (icon_id) WHERE icon_id IS NOT NULL;


CREATE TABLE books (
    id              BIGSERIAL PRIMARY KEY,
    publication_id  BIGINT       NOT NULL UNIQUE REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    category_id     INT          NOT NULL REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    publisher       VARCHAR(255),
    pages           SMALLINT     NOT NULL CHECK (pages > 0),
    isbn            CHAR(17)     UNIQUE CHECK (isbn ~ '^\d{3}-\d{1}-\d{3}-\d{5}-\d{1}$'),
    content_id      UUID         NOT NULL REFERENCES files (id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE INDEX idx_books_category_id ON books (category_id);
CREATE INDEX idx_books_content_id  ON books (content_id);


CREATE TABLE books_chapters (
    id              BIGSERIAL PRIMARY KEY,
    book_id         BIGINT       NOT NULL REFERENCES books (id) ON DELETE CASCADE ON UPDATE CASCADE,
    chapter_number  SMALLINT     NOT NULL CHECK (chapter_number > 0),
    title           VARCHAR(255) NOT NULL,
    page_start      SMALLINT     CHECK (page_start > 0),

    CONSTRAINT uq_books_chapters_book_number UNIQUE (book_id, chapter_number)
);


CREATE TYPE article_base AS ENUM ('content', 'book');

CREATE TABLE articles (
    id              BIGSERIAL PRIMARY KEY,
    publication_id  BIGINT       NOT NULL UNIQUE REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    doi             VARCHAR(255) UNIQUE CHECK (doi ~ '^10\.\d+\/\d+$'),
    type            article_base NOT NULL DEFAULT 'content',
    content         TEXT,
    book_id         BIGINT       REFERENCES books (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    page_start      SMALLINT     CHECK (page_start > 0),
    page_end        SMALLINT     CHECK (page_end > 0),

    CONSTRAINT articles_type_consistency CHECK (
        (type = 'content'
            AND content    IS NOT NULL
            AND book_id    IS NULL
            AND page_start IS NULL
            AND page_end   IS NULL)
        OR
        (type = 'book'
            AND content    IS NULL
            AND book_id    IS NOT NULL
            AND page_start IS NOT NULL
            AND page_end   IS NOT NULL
            AND page_end >= page_start)
    )
);

CREATE INDEX idx_articles_book_id ON articles (book_id) WHERE book_id IS NOT NULL;


CREATE TABLE comments (
    id              BIGSERIAL PRIMARY KEY,
    publication_id  BIGINT        NOT NULL REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    content         VARCHAR(2000) NOT NULL,
    created_at      TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    creator_id      BIGINT        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    is_active       BOOLEAN       NOT NULL DEFAULT TRUE,
    parent_id       BIGINT,

    CONSTRAINT uq_comments_id_publication UNIQUE (id, publication_id),

    CONSTRAINT fk_comments_parent
        FOREIGN KEY (parent_id, publication_id)
        REFERENCES comments (id, publication_id)
        ON DELETE NO ACTION ON UPDATE CASCADE
);

CREATE INDEX idx_comments_publication_created ON comments (publication_id, created_at);
CREATE INDEX idx_comments_parent              ON comments (parent_id, created_at) WHERE parent_id IS NOT NULL;
CREATE INDEX idx_comments_creator             ON comments (creator_id)            WHERE creator_id IS NOT NULL;


CREATE TABLE comments_likes (
    user_id     BIGINT NOT NULL REFERENCES users (id)    ON DELETE CASCADE ON UPDATE CASCADE,
    comment_id  BIGINT NOT NULL REFERENCES comments (id) ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT comments_likes_pk PRIMARY KEY (user_id, comment_id)
);

CREATE INDEX idx_comments_likes_comment ON comments_likes (comment_id);


CREATE TABLE ratings (
    publication_id  BIGINT   NOT NULL REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    user_id         BIGINT   NOT NULL REFERENCES users (id)        ON DELETE CASCADE ON UPDATE CASCADE,
    rating          SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),

    CONSTRAINT rating_pk PRIMARY KEY (publication_id, user_id)
);

CREATE INDEX idx_ratings_user ON ratings (user_id);


CREATE TABLE saved_publications (
    user_id         BIGINT      NOT NULL REFERENCES users (id)        ON DELETE CASCADE ON UPDATE CASCADE,
    publication_id  BIGINT      NOT NULL REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT saved_publications_pk PRIMARY KEY (user_id, publication_id)
);

CREATE INDEX idx_saved_publications_publication ON saved_publications (publication_id);
CREATE INDEX idx_saved_publications_user_recent ON saved_publications (user_id, created_at DESC);


CREATE TABLE users_read (
    user_id         BIGINT      NOT NULL REFERENCES users (id)        ON DELETE CASCADE ON UPDATE CASCADE,
    publication_id  BIGINT      NOT NULL REFERENCES publications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    page_num        SMALLINT    NOT NULL DEFAULT 1 CHECK (page_num > 0),
    is_closed       BOOLEAN     NOT NULL DEFAULT FALSE,
    last_read_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT read_status_pk PRIMARY KEY (user_id, publication_id)
);

CREATE INDEX idx_users_read_publication ON users_read (publication_id);
CREATE INDEX idx_users_read_user_recent ON users_read (user_id, last_read_at DESC);


CREATE TABLE publications_users (
    user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    publication_id  BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE ON UPDATE CASCADE,
    is_redactor     BOOLEAN NOT NULL DEFAULT FALSE,
    is_active       BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ NOT NULL DEFAULT 'infinity',

    CONSTRAINT pubs_users_pk PRIMARY KEY (user_id, publication_id)
);

COMMIT;