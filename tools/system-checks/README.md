# Shared system checks

`flx-system-checks.php` is the shared system-checks screen copied into Flux Donate, Flux Forms, Flux Mail and Flux Speed. The first copy that loads defines the functions; the others stay byte-identical.

Edit `tools/system-checks/flx-system-checks.php` only. `consumers.json` lists the four copy paths (Flux Forms uses a nested directory).

```sh
python3 tools/system-checks/sync.py
python3 tools/system-checks/sync.py --check
```

With no arguments the source is written over each copy. `--check` writes nothing, exits non-zero and names any copy that differs or is missing. CI runs the same check.
