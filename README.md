# FRCF Course Manager

Modul WordPress pentru gestionarea și afișarea cursurilor FRCF. Permite adăugarea manuală a cursurilor în panoul de administrare, gestionarea organizatorilor, locațiilor și categoriilor (categoriile nu sunt afișate public), filtrarea după locație și ascunderea automată a evenimentelor expirate.

## Instalare

1. Copiază directorul `frcf-course-manager` în `wp-content/plugins/`.
2. Activează modulul din **Plugins → FRCF Course Manager**.
3. La activare se creează tabela necesară în baza de date și se generează fișierele de resurse.

## Configurare în WordPress

În panoul de administrare vei găsi meniul **FRCF Cursuri**. De aici poți:

- adăuga, edita sau șterge cursuri;
- ajusta numărul de coloane și cursuri pe pagină din **FRCF Cursuri → Setări**.
- gestiona organizatorii, locațiile și categoriile cursurilor.

## Shortcodes

### `[frcf_courses]`

Folosește shortcode-ul `[frcf_courses]` pentru a afișa lista de cursuri.

Exemple:

```
[frcf_courses]
[frcf_courses columns="4"]
[frcf_courses location="București" limit="5"]
[frcf_courses show_all="yes"]
[frcf_courses debug="yes"]
```

Atribute disponibile:

- `columns` – numărul de coloane (2–4).
- `location` – afișează doar cursurile dintr-o anumită locație.
- `category` / `categorie` – afișează doar cursurile dintr-o anumită categorie.
- `limit` – numărul maxim de cursuri returnate.
- `show_all` – `yes` pentru a afișa și cursurile expirate.
- `debug` – `yes` afișează informații de depanare.

### `[frcf_courses_by_category]`

Shortcode-ul `[frcf_courses_by_category]` afișează cursurile grupate și sortate după categorie.

Exemple:

```
[frcf_courses_by_category]
[frcf_courses_by_category columns="4"]
[frcf_courses_by_category location="București" limit="5"]
[frcf_courses_by_category show_all="yes"]
```

Atribute disponibile:

- `columns` – numărul de coloane (2–4).
- `location` – afișează doar cursurile dintr-o anumită locație.
- `limit` – numărul maxim de cursuri returnate.
- `show_all` – `yes` pentru a afișa și cursurile expirate.

Instrucțiunile pentru aceste shortcode-uri sunt disponibile și în pagina de setări a modulului.

## Licență

Acest modul este distribuit sub licența [GPL v2 sau mai recentă](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

