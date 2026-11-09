import sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'site-translations'))
from repair_transport_artifacts import clean,clean_key

class TransportArtifacts(unittest.TestCase):
 def test_title(self):self.assertEqual(clean(r'\nFlux Forms\n'),'Flux Forms')
 def test_repr_quotes(self):self.assertEqual(clean(r"\nFlux Forms\'s settings\n"),"Flux Forms's settings")
 def test_partial_response(self):
  self.assertEqual(clean("[['Add forms\\n"),'Add forms')
  self.assertEqual(clean("\\nAdd forms', 'tr']]"),'Add forms')
 def test_complete_response(self):self.assertEqual(clean("[['Add forms', 'tr']]"),'Add forms')
 def test_plural_and_context(self):self.assertEqual(clean_key('button\4\\n%d form\\n\0\\n%d forms\\n'),'button\4%d form\0%d forms')
 def test_code_untouched(self):
  for value in (r'Use \n in a string',r'C:\new\folder','[[example]]',r'\x00',r'/a\nb/'):
   self.assertEqual(clean(value),value)
 def test_placeholders_and_markup(self):self.assertEqual(clean(r'\n<a href="%1$s">%2$s</a>\n'),'<a href="%1$s">%2$s</a>')
 def test_no_empty_header(self):self.assertEqual(clean(r'\n'),r'\n')

if __name__=='__main__':unittest.main()
