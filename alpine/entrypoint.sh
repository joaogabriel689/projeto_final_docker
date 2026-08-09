#!/bin/sh
set -e


printenv | sed -n "s/^\(MYSQL_[A-Z_]*\)=\(.*\)$/export \1='\2'/p" > /etc/container.env
chmod 600 /etc/container.env

exec "$@"