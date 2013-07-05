phppstts
========

Do some stuff with PrestaShop Translation Packs, in particular, enables you to quickly see the differences between two translation packs.

#Usage

``` sh
git clone https://github.com/djfm/phppstts.git
php phppstts/app.php --tool diffpack pack_1.gzip pack_2.gzip
```

This will output a file named pack_1.gzip_pack2.gzip.diff.csv in the current directory.

The CSV has 4 columns:
- Key  (the translation key)
- Same (YES if the translations in both packs are the same, NO otherwise)
- From (the translation in pack_1)
- To   (the translation in pack_2)

This currently doesn't diff the emails, but that can be implemented easily!
