# Question import format

Match the header row exactly; the column order does not matter but the names do.

## TWK and TIU

Header: `category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,key,explanation`

```csv
category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,key,explanation
twk,pilar_negara,"Pancasila sebagai dasar negara mengandung konsekuensi bahwa","Negara boleh mengabaikannya","Hukum harus bersumber padanya","Pancasila hanya simbol","Nilai Pancasila bersifat sementara","Pancasila hanya untuk pejabat",B,"Pancasila adalah sumber dari segala sumber hukum, jadi setiap aturan harus dapat ditelusuri ke sana."
tiu,deret_angka,"Lanjutkan pola 3, 6, 11, 18, ...","25","26","27","28","29",C,"Selisihnya bertambah dua tiap langkah: 3, 5, 7, 9, maka berikutnya 27."
```

Rules: `category` is `twk`, `tiu`, or `tkp`. `subcategory` must be one of the keys listed in
`config/cpns.php`. `key` is a single letter A–E. `explanation` may be empty.

An empty `explanation` still needs its cell: end the row with a trailing comma, or leave the
cell between two delimiters explicitly empty. A row that simply stops one cell short is one
short of the header's count, and the import refuses it by line with both counts named — the
trailing comma is the fix. A keyed row with nothing to explain therefore looks like this:

```csv
category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,key,explanation
twk,pilar_negara,"Siapa yang menyampaikan rumusan Pancasila pertama kali?",Soekarno,Mohammad Hatta,Ki Bagus Hadikusumo,Kasman Singodimedjo,Achmad Soebardjo,A,
```

The final comma is what keeps the row at ten cells with the last one empty; drop it and the
line arrives with nine.

## TKP

Header: `category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,score_a,score_b,score_c,score_d,score_e`
and no `key` column at all, because in TKP every option is defensible and only the weight
separates them.

```csv
category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,score_a,score_b,score_c,score_d,score_e
tkp,pelayanan_publik,"Seorang warga mengeluh keras di loketmu karena berkasnya kurang, sikapmu adalah","Menyuruhnya pulang dan kembali besok","Meminta rekan lain melayaninya","Menjelaskan kekurangan berkas dengan tenang lalu menunjukkan cara melengkapinya","Menyebut aturan dan menyuruhnya membaca sendiri","Mendiamkannya sampai ia berhenti mengeluh",1,3,5,2,4
```

The five scores must be exactly 1, 2, 3, 4 and 5 — each used once. Two options sharing a
weight makes the item worthless, because the subtest is scored by the weight of the chosen
option, and the importer refuses the whole batch when it finds one.

## Mixed blocks

One header row describes one shape, so a block that pastes TWK/TIU rows and TKP rows together
keeps the header and puts each TKP row's five weights in its last five cells — which is where
the TKP header above puts them anyway. A row whose own shape is not named in the header gets
nothing from the cells before its tail, so a keyed row and a weighted row can sit in the same
paste. Splitting the batch in two works just as well if you would rather be explicit.

## Delimiter

A pasted block pasted out of a spreadsheet uses tabs; a CSV file uses commas. The parser
picks the delimiter by looking at the header row, so both work without a setting.

## What happens on error

Every row is checked before anything is written. The preview lists the failing line numbers
with the reason, and the import button stays disabled: **one bad row means nothing is
imported**. That is deliberate — a half-imported bank hides broken items until a participant
meets one, which is much more expensive than a refused import. The commit endpoint re-checks
the whole block itself, so posting straight to it without a preview cannot slip a bad row in.

Every line must also carry exactly the header's cell count or, in a mixed block, exactly its
own shape's count — the 8 shared cells plus 2 for a keyed row, 8 shared plus 5 for a weighted
one. Any other count is refused by line with both counts named, rather than padded or sliced
into an item with its cells shifted. An unquoted comma inside a stem is the usual way to
arrive at one: quote the stem and the count comes back.
