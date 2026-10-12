#!/usr/bin/env bash
# Contrôle de la prod MonAvisPro, sans compte ni donnée réelle.
# Usage : bin/audit-prod.sh [https://monavispro-production.up.railway.app]
# Attention : envoie quelques connexions ratées avec des e-mails inventés (déclenche les limiteurs, c'est voulu).

set -u
BASE="${1:-https://monavispro-production.up.railway.app}"
OK=0; KO=0; WARN=0

ok()   { echo "  OK    $1"; OK=$((OK+1)); }
ko()   { echo "  KO    $1"; KO=$((KO+1)); }
warn() { echo "  ??    $1"; WARN=$((WARN+1)); }

code()    { curl -s -o /dev/null -w "%{http_code}" --max-time 20 "$@"; }
headers() { curl -s -D - -o /dev/null --max-time 20 "$@"; }
has()     { grep -qi "^$1" <<<"$2"; }

echo "== Cible : $BASE"

echo "== HTTPS et en-têtes de sécurité"
H=$(headers "$BASE/")
for h in "content-security-policy" "strict-transport-security" "x-frame-options: deny" "x-content-type-options: nosniff" "referrer-policy" "permissions-policy"; do
  has "$h" "$H" && ok "en-tête $h" || ko "en-tête $h absent"
done
has "x-powered-by" "$H" && ko "X-Powered-By exposé (version de PHP visible)" || ok "pas de X-Powered-By"
has "server: " "$H" && warn "en-tête Server présent : $(grep -i '^server:' <<<"$H" | tr -d '\r')" || ok "pas d'en-tête Server"
[ "$(code "http://${BASE#https://}/")" != "200" ] && ok "HTTP ne sert pas la page en clair" || ko "la page répond en HTTP non chiffré"

echo "== Accès sans être connecté"
[ "$(code "$BASE/api/establishments")" = "401" ] && ok "API sans jeton -> 401" || ko "API sans jeton ne renvoie pas 401"
C=$(code "$BASE/dashboard"); [[ "$C" = "302" || "$C" = "303" ]] && ok "/dashboard redirige vers la connexion" || ko "/dashboard répond $C"
C=$(code "$BASE/google/connect"); [[ "$C" = "302" || "$C" = "303" ]] && ok "/google/connect protégé" || ko "/google/connect répond $C"

echo "== Fichiers qui ne doivent jamais être servis"
for p in "/.env" "/.env.local" "/config/jwt/private.pem" "/composer.json" "/_profiler" "/.git/config" "/var/log/prod.log"; do
  C=$(code "$BASE$p"); [[ "$C" = "404" || "$C" = "403" ]] && ok "$p -> $C" || ko "$p répond $C"
done

echo "== Messages d'erreur"
B=$(curl -s --max-time 20 "$BASE/api/route-inexistante")
grep -qiE "stack|trace|exception|\.php" <<<"$B" && ko "l'erreur API laisse fuiter des détails techniques" || ok "erreur API neutre"
B=$(curl -s --max-time 20 "$BASE/page-inexistante-$RANDOM")
grep -qiE "stack trace|symfony\\\\|\.php on line" <<<"$B" && ko "la page 404 laisse fuiter des détails techniques" || ok "page 404 neutre"

echo "== Limiteur de débit sur la connexion (API)"
EMAIL="audit-$RANDOM-$RANDOM@example.invalid"
GOT429=no
for i in 1 2 3 4 5 6 7; do
  C=$(code -X POST -H "Content-Type: application/json" -d "{\"email\":\"$EMAIL\",\"password\":\"mauvais-$i\"}" "$BASE/api/auth/login")
  [ "$C" = "429" ] && GOT429=yes && break
done
[ "$GOT429" = yes ] && ok "connexion bloquée après plusieurs échecs (429)" || ko "aucun blocage après 7 échecs"

echo "== Contournement du limiteur avec X-Forwarded-For (faille trouvée sur Cap Monta)"
EMAIL="audit-$RANDOM-$RANDOM@example.invalid"
GOT429=no
for i in 1 2 3 4 5 6 7; do
  C=$(code -X POST -H "Content-Type: application/json" -H "X-Forwarded-For: 10.$RANDOM.$((RANDOM % 255)).$i" \
      -d "{\"email\":\"$EMAIL\",\"password\":\"mauvais-$i\"}" "$BASE/api/auth/login")
  [ "$C" = "429" ] && GOT429=yes && break
done
[ "$GOT429" = yes ] && ok "changer X-Forwarded-For ne contourne pas le limiteur" || ko "LIMITEUR CONTOURNABLE : une fausse IP dans X-Forwarded-For remet le compteur à zéro"

echo "== Performance et cache (après déploiement de fix/audit-octobre)"
CSS=$(curl -s --max-time 20 "$BASE/" | grep -oE '/css/style\.css\?v=[^"]+' | head -1)
if [ -n "$CSS" ]; then
  H=$(headers "$BASE$CSS")
  grep -qi "^cache-control:.*immutable" <<<"$H" && ok "CSS en cache longue durée" || warn "CSS sans cache longue durée"
  H=$(headers -H "Accept-Encoding: gzip, zstd" "$BASE$CSS")
  grep -qi "^content-encoding:" <<<"$H" && ok "CSS compressé" || warn "CSS non compressé"
else
  warn "lien CSS versionné introuvable sur l'accueil"
fi
[ "$(code "$BASE/robots.txt")" = "200" ] && ok "robots.txt présent" || warn "robots.txt absent"
T=$(curl -s -o /dev/null -w "%{time_total}" --max-time 20 "$BASE/")
awk "BEGIN{exit !($T < 1.5)}" && ok "accueil servi en ${T}s" || warn "accueil lent : ${T}s"

echo
echo "== Bilan : $OK OK, $KO KO, $WARN à regarder"
[ "$KO" -eq 0 ]
