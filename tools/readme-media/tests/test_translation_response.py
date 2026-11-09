import sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'site-translations'))
from translate_all import translation_text
class ResponseTests(unittest.TestCase):
 def test_direct(self):self.assertEqual('Hello',translation_text('Hello'))
 def test_flat(self):self.assertEqual('Hello',translation_text(['Hello']))
 def test_client_pair(self):self.assertEqual('Hello',translation_text([['Hello','tr']]))
 def test_client_segments(self):self.assertEqual('Hello world',translation_text([['Hello ','tr'],['world','tr']]))
 def test_api_segments(self):self.assertEqual('Hello world',translation_text([[['Hello ','Merhaba',None,None],['world','dünya',None,None]],None,'tr']))
 def test_unknown_rejected(self):
  for value in [None,{},[],[None],[[None,'tr']]]:
   with self.assertRaises(ValueError):translation_text(value)
if __name__=='__main__':unittest.main()
