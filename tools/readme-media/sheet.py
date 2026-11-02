import sys,glob,os
from PIL import Image,ImageDraw
d,lang,out=sys.argv[1],sys.argv[2],sys.argv[3]
fs=sorted(glob.glob(f'{d}/*-{lang}.jpg'))
th=[]
for f in fs:
  im=Image.open(f); im.thumbnail((420,420)); c=Image.new('RGB',(430,445),'white'); c.paste(im,(5,20)); ImageDraw.Draw(c).text((5,3),os.path.basename(f),fill='black'); th.append(c)
cols=3; rows=(len(th)+cols-1)//cols; g=Image.new('RGB',(430*cols,445*rows),'#ddd')
for i,t in enumerate(th): g.paste(t,((i%cols)*430,(i//cols)*445))
g.save(out)
