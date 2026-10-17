#!/usr/bin/env bash
# Installe Wanllan sur un serveur AWS Lightsail neuf (Ubuntu 24.04 LTS).
#
# À coller dans le terminal du serveur (bouton « Se connecter avec SSH ») :
#   curl -fsSL https://raw.githubusercontent.com/MohameBalde/Replica/main/deploy/lightsail/install.sh | sudo bash -s -- wanllan.com
#
# On peut le relancer sans risque : il garde la clé de l'app, le mot de passe
# de la base et les réglages déjà faits. Si la connexion coupe en route,
# reconnectez-vous et relancez la même commande.
set -euo pipefail

# Tout le script est entre accolades : bash le lit en entier avant de
# commencer, donc aucune commande ne peut avaler la suite par « curl | bash ».
{

DOMAIN="${1:-}"
BRANCH="${2:-main}"
GITHUB_REPO="${GITHUB_REPO:-MohameBalde/Replica}"
APP_USER=wanllan
BASE=/var/www/wanllan

say() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
die() { printf '\n\033[1;31mErreur : %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "lancez la commande avec sudo."
[[ -n "$DOMAIN" ]] || die "donnez le nom de domaine, par exemple : ... | sudo bash -s -- wanllan.com"
[[ "$DOMAIN" =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]] || die "nom de domaine invalide : $DOMAIN (sans https://, sans www)"
grep -q 'VERSION_ID="24.04"' /etc/os-release || die "ce script est prévu pour Ubuntu 24.04 LTS."

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a

say "1/10 Heure de Conakry et mémoire d'appoint (swap)"
timedatectl set-timezone Africa/Conakry
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
sysctl -q -w vm.swappiness=10
echo 'vm.swappiness=10' > /etc/sysctl.d/90-wanllan.conf

say "2/10 Mises à jour et logiciels (5 à 10 minutes)"
# Termine une installation interrompue (connexion coupée pendant un premier essai).
dpkg --configure -a
apt-get update -q
apt-get -y -q -o Dpkg::Options::=--force-confold upgrade
apt-get -y -q install ca-certificates curl git unzip jq gzip \
    nginx mysql-server \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-opcache php8.3-sqlite3 composer \
    ufw fail2ban certbot unattended-upgrades
if ! command -v node >/dev/null || [[ "$(node -p 'process.versions.node.split(".")[0]')" -lt 22 ]]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x -o /tmp/nodesource_setup.sh
    bash /tmp/nodesource_setup.sh >/dev/null
    apt-get -y -q install nodejs
fi

say "3/10 Utilisateur de l'app et dossiers"
id "$APP_USER" >/dev/null 2>&1 || useradd --create-home --shell /bin/bash "$APP_USER"
install -d -o "$APP_USER" -g "$APP_USER" -m 755 "$BASE" "$BASE/releases" "$BASE/shared" "$BASE/state"
for d in app/public app/private framework/cache/data framework/sessions framework/views logs; do
    install -d -o "$APP_USER" -g "$APP_USER" -m 775 "$BASE/shared/storage/$d"
done
install -d -m 755 /var/www/letsencrypt /etc/wanllan
install -d -m 700 /var/backups/wanllan

say "4/10 Code de Wanllan depuis GitHub ($GITHUB_REPO, branche $BRANCH)"
if [[ -d "$BASE/repo/.git" ]]; then
    sudo -u "$APP_USER" git -C "$BASE/repo" fetch --quiet origin "$BRANCH"
    sudo -u "$APP_USER" git -C "$BASE/repo" checkout --quiet -B "$BRANCH" "origin/$BRANCH"
else
    sudo -u "$APP_USER" git clone --quiet --branch "$BRANCH" "https://github.com/$GITHUB_REPO.git" "$BASE/repo"
fi
SRC="$BASE/repo/deploy/lightsail"
[[ -d "$SRC" ]] || die "dossier deploy/lightsail absent de la branche $BRANCH."

cat > /etc/wanllan/deploy.conf <<EOF
# Lu par les commandes wanllan-*. Modifier puis : sudo wanllan-deploy --force
APP_USER=$APP_USER
BASE=$BASE
DOMAIN=$DOMAIN
BRANCH=$BRANCH
GITHUB_REPO=$GITHUB_REPO
# Tous les tests tournent sur le serveur avant chaque mise en ligne (1 = oui).
RUN_TESTS=1
# En plus, attendre ce test GitHub Actions (laisser vide tant que GitHub Actions
# ne tourne pas sur le compte ; mettre « tests » quand il marche).
CHECK_NAME=
KEEP_RELEASES=5
# Seulement si le dépôt devient privé : un jeton GitHub en lecture seule.
GITHUB_TOKEN=
EOF
chown root:"$APP_USER" /etc/wanllan/deploy.conf
chmod 640 /etc/wanllan/deploy.conf

install -m 755 "$SRC"/bin/* /usr/local/bin/

say "5/10 Base de données MySQL"
install -m 644 "$SRC/conf/mysql.cnf" /etc/mysql/mysql.conf.d/zz-wanllan.cnf
systemctl restart mysql
ENV_FILE="$BASE/shared/.env"
if [[ -f "$ENV_FILE" ]]; then
    DB_PASSWORD="$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)"
else
    DB_PASSWORD="$(openssl rand -hex 24)"
fi
mysql <<SQL
CREATE DATABASE IF NOT EXISTS wanllan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'wanllan'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
CREATE USER IF NOT EXISTS 'wanllan'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER 'wanllan'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER 'wanllan'@'127.0.0.1' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON wanllan.* TO 'wanllan'@'localhost';
GRANT ALL PRIVILEGES ON wanllan.* TO 'wanllan'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

say "6/10 Réglages de production (.env)"
if [[ ! -f "$ENV_FILE" ]]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    sed -e "s|__DOMAIN__|$DOMAIN|g" -e "s|__APP_KEY__|$APP_KEY|" -e "s|__DB_PASSWORD__|$DB_PASSWORD|" \
        "$SRC/conf/env.production" > "$ENV_FILE"
fi
chown "$APP_USER:$APP_USER" "$ENV_FILE"
chmod 640 "$ENV_FILE"

say "7/10 PHP 8.3"
install -m 644 "$SRC/conf/php.ini" /etc/php/8.3/fpm/conf.d/99-wanllan.ini
install -m 644 "$SRC/conf/php-fpm-pool.conf" /etc/php/8.3/fpm/pool.d/wanllan.conf
[[ -f /etc/php/8.3/fpm/pool.d/www.conf ]] && mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.off
systemctl restart php8.3-fpm
install -m 440 "$SRC/conf/sudoers" /etc/sudoers.d/wanllan
visudo -cqf /etc/sudoers.d/wanllan || die "règle sudo invalide."

say "8/10 Nginx, pare-feu, mises à jour de sécurité automatiques"
install -m 644 "$SRC/conf/nginx-app.conf" /etc/nginx/snippets/wanllan-app.conf
install -m 644 "$SRC/conf/nginx-tuning.conf" /etc/nginx/conf.d/wanllan-tuning.conf
rm -f /etc/nginx/sites-enabled/default
if [[ -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" ]]; then wanllan-nginx https; else wanllan-nginx http; fi
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable >/dev/null
systemctl enable --now fail2ban >/dev/null
install -m 644 "$SRC/conf/unattended-upgrades" /etc/apt/apt.conf.d/52wanllan-unattended
printf 'APT::Periodic::Update-Package-Lists "1";\nAPT::Periodic::Unattended-Upgrade "1";\n' > /etc/apt/apt.conf.d/20auto-upgrades
install -m 644 "$SRC/conf/logrotate" /etc/logrotate.d/wanllan

say "9/10 Mises en ligne automatiques, sauvegardes, tâches planifiées"
install -m 644 "$SRC"/systemd/* /etc/systemd/system/
install -m 644 "$SRC/conf/cron" /etc/cron.d/wanllan
systemctl daemon-reload
systemctl enable --now wanllan-deploy.timer wanllan-backup.timer >/dev/null

say "10/10 Première mise en ligne (les tests tournent d'abord, 2 à 4 minutes)"
wanllan-deploy --wait || die "première mise en ligne impossible. Tapez : sudo wanllan-status"

if wanllan-https --check >/dev/null 2>&1; then
    say "Le domaine pointe déjà ici : activation du HTTPS"
    wanllan-https
fi

if grep -q '^WANLLAN_SUPPORT_WHATSAPP=$' "$ENV_FILE"; then
    say "Vos numéros (abonnements et support)"
    wanllan-config
fi

IP="$(curl -fsS --max-time 10 https://checkip.amazonaws.com || echo 'IP-du-serveur')"
printf '\n\033[1;32mWanllan est installé.\033[0m\n'
cat <<EOF
  Adresse provisoire : http://$IP
  Votre domaine      : https://$DOMAIN (après le HTTPS)

Commandes utiles :
  sudo wanllan-status      l'état du site, de la dernière mise en ligne et du disque
  sudo wanllan-https       active le HTTPS quand $DOMAIN pointe vers $IP
  sudo wanllan-config      change les numéros Orange Money et WhatsApp du support
  artisan wanllan:abonnement <boutique> merchant 1 --ref=<n° transaction>
EOF
exit 0
}
