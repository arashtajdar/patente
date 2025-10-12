create table if not exists question
(
    id              bigint unsigned auto_increment
        primary key,
    text            text null,
    image           text null,
    parent_number   int  null,
    question_number int  null,
    answer          text null,
    constraint id
        unique (id)
);

create table if not exists translation
(
    id      bigint unsigned auto_increment
        primary key,
    italian text null,
    english text null,
    persian text null,
    constraint id
        unique (id)
);

create table if not exists user_question_stats
(
    user_id     int                                 not null,
    question_id int                                 not null,
    wrong       int       default 0                 not null,
    correct     int       default 0                 not null,
    updated_at  timestamp default CURRENT_TIMESTAMP not null on update CURRENT_TIMESTAMP,
    primary key (user_id, question_id)
);

create table if not exists user_translation_stats
(
    user_id        int                                 not null,
    translation_id int                                 not null,
    correct        int       default 0                 not null,
    updated_at     timestamp default CURRENT_TIMESTAMP not null on update CURRENT_TIMESTAMP,
    primary key (user_id, translation_id)
);

create table if not exists users
(
    id       bigint unsigned auto_increment
        primary key,
    username text null,
    password text null,
    constraint id
        unique (id)
);

