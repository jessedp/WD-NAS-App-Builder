FROM debian:trixie-slim

ENV LANG=C.UTF-8

# Only what packaging needs: curl to fetch the upstream release archives,
# libxml2 for mksapkg-OS5, openssl for signing, and the archive tools to unpack
# what we download. Nothing here is compiled for the NAS - upstream ships the
# binaries we wrap - so the base image never reaches the device.
RUN apt-get update && \
	apt-get install -y --no-install-recommends \
	ca-certificates \
	curl \
	libxml2 \
	openssl \
	xz-utils \
	tar \
	gzip && \
	rm -rf /var/lib/apt/lists/*

# mksapkg-OS5 signs packages with Blowfish, which OpenSSL 3 moved out of the
# default provider, so the legacy one has to be switched on. Debian ships
# [default_sect] deactivated and warns that activating any other provider
# disables it implicitly, so both are enabled explicitly. The trailing enc call
# is a smoke test: if Blowfish ever goes away the image fails to build rather
# than silently producing unsigned packages.
RUN sed -i \
	-e '/^\[provider_sect\]/,/^\[/ s/^default = default_sect/default = default_sect\nlegacy = legacy_sect/' \
	-e '/^\[default_sect\]/,/^\[/ s/^# activate = 1/activate = 1/' \
	/etc/ssl/openssl.cnf && \
	printf '\n[legacy_sect]\nactivate = 1\n' >> /etc/ssl/openssl.cnf && \
	echo smoketest | openssl enc -bf-cbc -k smoketest > /dev/null

# Volume that will point to the whole repository
VOLUME /data

WORKDIR /data
