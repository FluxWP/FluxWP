#!/bin/bash
# Bir paketin tanitim medyasini ve README'sini bastan uretir.
# Kullanim: bash tools/readme-media/run.sh <slug> [plugins|themes] [--no-gif] [--no-seed]
# Adimlar: seed -> EN/TR ekran goruntuleri -> EN/TR GIF -> docs/ yerlestirme -> README.md + README.tr.md -> zip kontrolu
set -e
H=$(cd "$(dirname "$0")" && pwd); REPO=$(cd "$H/../.." && pwd); W=${WPENV:-/tmp/wpenv}
s=$1; k=${2:-plugins}; [ -z "$s" ] && { echo "slug gerekli"; exit 1; }
C=$H/cfg/$s.json; [ -f "$C" ] || { echo "yok: $C"; exit 1; }
bash "$H/serve.sh" >/dev/null
# "snapshot": true -> baska bir site gibi kurulan paketler (or. bir on ayar) icin veritabani once yedeklenir, sonda geri konur
if python3 -c "import json,sys;sys.exit(0 if json.load(open('$C')).get('snapshot') else 1)"; then
  DBD=$(ls -d "$W"/site/wp-content/database/.ht.*/ | head -1); rm -rf "$W/snap"; cp -a "$DBD" "$W/snap"
  trap 'rm -rf "$DBD"; cp -a "$W/snap" "$DBD"; echo "veritabani geri yuklendi"' EXIT
fi
if [[ " $* " != *" --no-seed "* ]]; then
  for f in $(python3 -c "import json;print(' '.join(json.load(open('$C')).get('seed',[])))"); do echo "seed $f"; php "$H/seed/$f"; done
fi
mkdir -p "$W/out/$s" "$W/gifs"
for l in en tr; do node "$H/shoot.js" "$C" $l "$W/out/$s" 2>&1 | grep -v "^ok" | tail -3 || true; done
if [[ " $* " != *" --no-gif "* ]]; then
  for l in en tr; do node "$H/gif.js" "$C" $l > "$W/gif-$s-$l.log" 2>&1; bash "$H/mkgif.sh" "$(tail -1 "$W/gif-$s-$l.log")" "$W/gifs/$s-$l.gif"; done
fi
bash "$H/place.sh" "$s" "$k"
python3 "$H/gen.py" "$H/text/$s.py"
cd "$REPO"; bash bin/build.sh "$k/$s" >/dev/null 2>&1 || true
n=$(unzip -l "dist/$s.zip" 2>/dev/null | grep -E " $s/(docs/|README)" | wc -l); echo "zip icinde docs/README: $n (0 olmali)"
bash bin/readme-table.sh README.md >/dev/null 2>&1 || true
echo "bitti: $k/$s  ->  git diff --stat $k/$s"
