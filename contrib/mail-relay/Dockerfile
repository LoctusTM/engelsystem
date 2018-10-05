FROM ubuntu:16.04



RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -q -y postfix rsyslog&&  postconf -e relayhost=[mailmaster.uni-wuerzburg.de] && postconf -e smtp_sasl_auth_enable=yes && postconf -e smtp_sasl_password_maps=/etc/postfix/sasl_passwd && postconf -e smtp_sasl_security_options=nonanonymous && postconf -e smtp_use_tls=yes && touch /etc/postfix/sasl_passwd && echo "[mail.physik.uni-wuerzburg.de] zapf:party43edding" > /etc/postfix/sasl_passwd && postmap /etc/postfix/sasl_passwd






EXPOSE 25



CMD ["sh", "-c", "service rsyslog start ; service postfix start ; tail -F /var/log/mail.log"]
