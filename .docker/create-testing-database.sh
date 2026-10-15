#!/usr/bin/env bash

mysql --user=docker --password="$MYSQL_DATABASE" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS testing;
    GRANT ALL PRIVILEGES ON \`testing%\`.* TO '$MYSQL_DATABASE'@'%';
EOSQL
