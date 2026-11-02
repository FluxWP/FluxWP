#!/bin/bash
# place.sh <slug> [plugins|themes]
s=$1; k=${2:-plugins}; H=$(cd "$(dirname "$0")" && pwd); W=${WPENV:-/tmp/wpenv}; R=$(cd "$H/../.." && pwd)/$k/$s; D=$R/docs
mkdir -p $D/images
L=/mnt/project-files/guncelleme-sistemi/logolar/$s.svg; [ -f $L ] || L=$R/assets/icon.svg; [ -f $L ] || L=$R/assets/images/logo/ikon.svg; [ -f $H/logo/$s.svg ] && L=$H/logo/$s.svg; [ -f "$3" ] && L=$3
python3 -I -c "
import re,sys
s=open('$L').read(); s=re.sub(r'<metadata>.*?</metadata>','',s,flags=re.S).replace(' xmlns:c2pa=\"http://c2pa.org/manifest\"','')
open('$D/logo.svg','w').write(s)"
cp $W/out/$s/*.jpg $D/images/
cp $W/gifs/$s-en.gif $D/demo.gif; cp $W/gifs/$s-tr.gif $D/demo-tr.gif
f=$R/.distignore; grep -q "^./docs$" $f 2>/dev/null || { [ -s $f ] && [ -n "$(tail -c1 $f)" ] && echo >> $f; printf '# GitHub tanitim dosyalari pakete girmez\n./docs\n./README.md\n./README.tr.md\n' >> $f; }
du -sh $D | cut -f1
ffmpeg -loglevel error -y -i $D/demo.gif -vf "fps=5,scale=320:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=64[p];[b][p]paletteuse=dither=bayer:bayer_scale=4" $D/thumb.gif
