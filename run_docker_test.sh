#!/usr/bin/env bash
docker build -t celery-php-test .
docker run --rm celery-php-test:latest ./dockerized_test.sh
