# Reflection Diary — API Docs

Andraws Matti · Sprint 3, updated Sprint 4

All routes start with `/api`. Send and expect JSON (`Accept: application/json`).

Every response has a `success` field. If something goes wrong you get `success: false` and a `message`, or for validation problems, a `422` with an `errors` list showing which field was wrong.

## The 6 competencies

Both the student and the assessor score the same 6 keys, each 1 to 5:

`contribution`, `communication`, `collaboration`, `agile`, `continuous`, `leadership`

These go in a `scores` object. It's optional — if you send `scores`, all 6 keys have to be there and each one is checked separately, so something like `contribution: 99` gets rejected.

---

## Reflections (the student's side)

**Create** — `POST /api/reflections`
Send `score` (1–5, required), `comment` (optional, max 1000 characters), and `scores` (optional). Returns the saved entry with a `201`.

**List** — `GET /api/reflections`
Newest first, 15 per page. Use `?per_page=20&page=2` to change that (max 100 per page). The `meta` part of the response tells you the total and how many pages there are.

**Get one** — `GET /api/reflections/{id}` *(new in Sprint 3)*
Returns the entry plus any assessor feedback on it, under `assessments`, and any evidence attached to it, under `evidence` (added in Sprint 4). This is the one the radar chart should use, since it gives both sets of scores in one go:

```json
{
  "success": true,
  "data": {
    "id": 2,
    "score": 4,
    "comment": "Sprint 2 - built the self-review scoring...",
    "scores": { "contribution": 4, "communication": 4, "collaboration": 4, "agile": 3, "continuous": 4, "leadership": 3 },
    "assessments": [
      {
        "id": 2,
        "score": 5,
        "feedback": "Caught the validation issue yourself...",
        "scores": { "contribution": 5, "communication": 4, "collaboration": 4, "agile": 4, "continuous": 4, "leadership": 4 }
      }
    ]
  }
}
```

If there's no assessment yet, `assessments` is just an empty list.

**Edit** — `PUT /api/reflections/{id}`
Send only what you want to change (`score`, `comment`, or `scores`). Returns `404` if the entry doesn't exist.

**Delete** — `DELETE /api/reflections/{id}`
Deletes the entry, and any assessments and evidence on it get deleted with it (including the uploaded files). Returns `404` if it doesn't exist.

---

## Assessments (the assessor's side)

**Create** — `POST /api/assessments`
Send `reflection_id` (required, has to be a real reflection), `score` (1–5, required), `feedback` (optional, max 1000 characters), and `scores` (optional, same 6 keys). Returns the saved assessment with a `201`.

Before Sprint 3 the assessor's `scores` were being quietly dropped — the column was in the database but never saved. That's fixed now.

**List** — `GET /api/assessments` *(new in Sprint 3)*
Same paging as reflections. Add `?reflection_id=5` to only get assessments for one reflection.

**Get one** — `GET /api/assessments/{id}` *(new in Sprint 3)*
Returns `404` if it doesn't exist.

---

## Evidence (files or links on a reflection) — new in Sprint 4

**Add evidence** — `POST /api/reflections/{id}/evidence`
Send it as a normal form upload (`multipart/form-data`), not JSON. Send **either** a `file` **or** a `link`, not both, plus an optional `description` (max 255 characters).

- `file`: max 10 MB. Allowed types: pdf, doc, docx, ppt, pptx, xls, xlsx, txt, csv, png, jpg, jpeg, gif, webp, zip. Both the file name and what's actually inside the file get checked, so renaming something to `.pdf` won't get it through.
- `link`: has to start with `http://` or `https://`.

Returns the saved evidence with a `201`. For files you get back `original_name`, `mime_type`, `size` (in bytes) and a `download_url`. For links, `download_url` is `null`.

Example from JavaScript:

```js
const form = new FormData();
form.append('file', fileInput.files[0]);   // or: form.append('link', 'https://...')
form.append('description', 'Sprint 3 report');

await fetch(`/api/reflections/${reflectionId}/evidence`, {
  method: 'POST',
  headers: { Accept: 'application/json' },  // don't set Content-Type, the browser does it
  body: form,
});
```

**List evidence** — `GET /api/reflections/{id}/evidence`
All evidence on one reflection, newest first. (It's also included in `GET /api/reflections/{id}`.)

**Download a file** — `GET /api/evidence/{id}/download`
Sends the file back with its original name. Returns `404` for links, since there's no file.

**Delete evidence** — `DELETE /api/evidence/{id}`
Removes it, and the stored file too if it has one.

**Heads up on file size:** PHP only allows 2 MB uploads by default, even though the API allows 10 MB. Anything bigger than PHP's limit comes back as a `422` saying the file failed to upload. To allow the full 10 MB, set these in `php.ini` (or in the server settings on AWS):

```
upload_max_filesize = 12M
post_max_size = 13M
```

**Where files are stored:** on Laravel's default disk, set by `FILESYSTEM_DISK` in `.env`. Locally that's `storage/app/private`. For AWS it can be switched to S3 by setting `FILESYSTEM_DISK=s3` plus the AWS keys (needs `composer require league/flysystem-aws-s3-v3`). The code doesn't need to change.

---

## Sample data for demos

To fill a database with demo data:

```
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

That adds 3 students and 1 assessor (all with the password `password`), 6 reflections with scores, and assessor feedback on 4 of them. The other 2 are left without feedback so you can show what an entry waiting for review looks like. A few reflections also have evidence links attached.

## Quick test from the terminal

```
curl -H "Accept: application/json" http://localhost:8000/api/reflections/1
```
