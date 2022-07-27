ARG DOCKWARE_VERSION

FROM dockware/dev:$DOCKWARE_VERSION

LABEL maintainer="PI-Ecommerce"
LABEL maintainer_email="pi-ecommerce@hipay.com"

ADD --chown=root:root bin/docker/images/shopware/entrypoint.sh /post-entrypoint.sh
RUN sudo chmod a+x /post-entrypoint.sh

CMD [ "/post-entrypoint.sh" ]
