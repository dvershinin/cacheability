FROM almalinux:9
ENV container docker
STOPSIGNAL SIGRTMIN+3

# Systemd cleanup
RUN (cd /lib/systemd/system/sysinit.target.wants/; for i in *; do [ $i == \
systemd-tmpfiles-setup.service ] || rm -f $i; done); \
rm -f /lib/systemd/system/multi-user.target.wants/*;\
rm -f /etc/systemd/system/*.wants/*;\
rm -f /lib/systemd/system/local-fs.target.wants/*; \
rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
rm -f /lib/systemd/system/basic.target.wants/*;\
rm -f /lib/systemd/system/anaconda.target.wants/*;

# Install dependencies and Varnish
# Note: getpagespeed-extras-varnish60 requires subscription, falling back to EPEL/AppStream for Varnish
RUN dnf -y update && \
    dnf -y install dnf-plugins-core epel-release && \
    dnf -y install varnish procps && \
    systemctl enable varnish && \
    dnf clean all

CMD ["/usr/sbin/init"]

