import copy
import unittest
from validator import validate, protected
from translate_all import protect_string, restore_string

class TranslationContractTests(unittest.TestCase):
    def setUp(self):
        self.source = {'_meta': {'format':'fluxwp-site-translations/v1', 'source_hash':'test'}, 'text': {'title':'Hello %s', 'empty':'', 'done':False}}
        self.target = {'_meta': {'format':'fluxwp-site-translations/v1', 'source_hash':'test', 'language':'ar', 'locale':'ar', 'direction':'rtl'}, 'text': {'title':'مرحبا %s', 'empty':'', 'done':False}}
    def test_valid_arabic(self):
        self.assertEqual(validate(self.target,self.source)['empty'],0)
    def test_immutable_and_missing_token_rejected(self):
        for field,value in [('done',True),('title','مرحبا'),('empty','extra')]:
            with self.subTest(field=field),self.assertRaises(ValueError):
                candidate=copy.deepcopy(self.target);candidate['text'][field]=value;validate(candidate,self.source)
    def test_protected_code_backslashes_restore_literally(self):
        text='Run `C:\\tools\\new` and [docs](https://example.test/a) with {email} and %1$s.'
        masked,tokens=protect_string(text)
        restored=restore_string(masked,tokens)
        self.assertEqual(protected(text),protected(restored))

if __name__=='__main__':unittest.main()
