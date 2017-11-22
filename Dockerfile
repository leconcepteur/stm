FROM php:7.1-fpm
MAINTAINER Jonathan Dube <leconcepteur@gmail.com>

# Bashrc
RUN echo "export LS_OPTIONS='--color=auto'" >> /root/.bashrc
RUN echo 'eval "`dircolors`"' >> /root/.bashrc
RUN echo "alias ls='ls $LS_OPTIONS'" >> /root/.bashrc
RUN echo "alias ll='ls $LS_OPTIONS -l'" >> /root/.bashrc
RUN echo "alias l='ls $LS_OPTIONS -lA'" >> /root/.bashrc
RUN echo "alias rm='rm -i'" >> /root/.bashrc
RUN echo "alias cp='cp -i'" >> /root/.bashrc
RUN echo "alias mv='mv -i'" >> /root/.bashrc

# Install lib dependencies
RUN apt-get update && \
    apt-get install -y git \
        curl \
        wget \
        subversion \
        unzip \
        libmcrypt-dev \
        libpng12-dev \
        libxml2-dev \
        libjpeg62-turbo-dev \
        libcurl4-gnutls-dev \
        nodejs-legacy \
        npm \
        ruby-full \
        make

# install compass
RUN gem install --no-rdoc --no-ri compass

# Docker PHP extension install
RUN docker-php-ext-install pdo pdo_mysql gd curl iconv mcrypt mbstring

# docker-php-ext-install phalcon doesn't work
RUN apt-get install -y gcc libpcre3-dev
RUN git clone --branch=phalcon-v2.0.0 --depth=1 https://github.com/phalcon/cphalcon.git
RUN cd cphalcon/build && ./install
RUN docker-php-ext-enable phalcon

# Install Composer and make it available in the PATH
RUN wget https://getcomposer.org/composer.phar && chmod +x composer.phar && mv composer.phar /usr/local/bin/composer

EXPOSE 9000
CMD ["php-fpm"]