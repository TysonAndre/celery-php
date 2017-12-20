FROM ubuntu:17.10

# Install php 7.1 and rabbitmq
RUN apt-get update -y && \
	apt-get install -y php php-pear php-dev wget git python-virtualenv make php-bcmath php-mbstring php-zip \
	librabbitmq4 librabbitmq-dev rabbitmq-server \
	redis-server && \
	apt-get clean -y

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    rm composer-setup.php && \
    mv composer.phar /usr/bin/composer

# bc.so and mbstring.so are dependencies of the latest php-amqplib release (affects unit tests).
# Install php-zip to avoid a slower/verbose git clone step
RUN yes '' | pecl install amqp
RUN echo "extension=amqp.so" > /etc/php/7.1/cli/conf.d/20-amqp.ini
#RabbitMQ

# Celery install
RUN virtualenv venv
RUN venv/bin/pip install celery redis

# Add this last, so that previous docker layers can be reused
RUN mkdir /opt/celery-php
WORKDIR /opt/celery-php
ADD composer.json /opt/celery-php/
RUN composer --no-interaction install --prefer-dist --no-progress && \
    composer --no-interaction validate --strict

ADD . /opt/celery-php/

# script: $(composer config bin-dir)/phpunit
