FROM ubuntu:20.04

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get --assume-yes update
RUN apt-get --assume-yes upgrade

# Common dependencies
RUN apt-get --assume-yes install software-properties-common

# spreed-recording dependencies
RUN apt-get --assume-yes install ffmpeg pulseaudio python3-pip xvfb
RUN pip3 install --upgrade requests

# firefox
RUN apt-get --assume-yes install firefox firefox-geckodriver

# chromium
# The phd/chromium repository for Ubuntu is used because since Ubuntu 20.04
# Chromium is provided as a snap package, and the equivalent PPA has been
# discontinued.
RUN echo "deb https://freeshell.de/phd/chromium/focal /" > /etc/apt/sources.list.d/phd-chromium.list
RUN apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 869689FE09306074
RUN apt-get update
RUN apt-get --assume-yes install chromium

COPY ./docker-compose/wrap_chromium_binary /opt/bin/wrap_chromium_binary
RUN /opt/bin/wrap_chromium_binary

# spreed-recording config
RUN useradd --create-home recording
COPY server.conf.in /etc/nextcloud-talk-recording/server.conf
RUN sed --in-place 's/#listen =.*/listen = 0.0.0.0:8000/' /etc/nextcloud-talk-recording/server.conf

# Deploy recording server
RUN mkdir --parents /tmp/recording
COPY src /tmp/recording/
COPY pyproject.toml /tmp/recording/
RUN python3 -m pip install /tmp/recording/

# Cleanup
RUN apt-get clean && rm --recursive --force /var/lib/apt/lists/* /tmp/* /var/tmp/*
RUN rm --recursive --force /tmp/recording

# Switch user and start the recording server
WORKDIR "/home/recording/"
USER "recording"
CMD ["python3", "-m", "nextcloud.talk.recording", "--config", "/etc/nextcloud-talk-recording/server.conf"]
