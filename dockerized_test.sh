#!/usr/bin/env bash
set -xeu
# Redis
/etc/init.d/redis-server start
# RabbitMQ
/etc/init.d/rabbitmq-server start
rabbitmqctl add_user gdr test
rabbitmqctl add_vhost celeryamqplib
rabbitmqctl set_permissions -p celeryamqplib gdr '.*' '.*' '.*'
rabbitmqctl add_vhost celerypecl
rabbitmqctl set_permissions -p celerypecl gdr '.*' '.*' '.*'
# Celery
/venv/bin/celery worker --config celeryamqplibconfig --workdir $PWD/testscenario --detach --pidfile celeryamqplib.pid
/venv/bin/celery worker --config celerypeclconfig --workdir $PWD/testscenario --detach --pidfile celerypecl.pid
/venv/bin/celery worker --config celeryredisconfig --workdir $PWD/testscenario --detach --pidfile celeryredis.pid
# Run the unit tests
vendor/bin/phpunit
