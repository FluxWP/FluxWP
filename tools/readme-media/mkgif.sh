#!/bin/bash
# mkgif.sh in.webm out.gif [width] [fps]
ffmpeg -v error -y -ss 1.0 -i "$1" -vf "fps=${4:-7},scale=${3:-800}:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=80:stats_mode=diff[p];[b][p]paletteuse=dither=none:diff_mode=rectangle" "$2"; du -k "$2" | cut -f1
