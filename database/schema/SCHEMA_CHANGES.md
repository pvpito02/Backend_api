# Évolutions du schéma SQL (Tâche 1)

Par rapport à `pointage_mairie_schema.sql` d’origine, ajouts pour couvrir l’admin et le mobile :

## Auth & sessions
- `users.last_logout_at`, `last_login_ip`, `last_user_agent`
- Table `personal_access_tokens` (Sanctum)
- Rôles `admin`, `sous_admin` (+ conservation `rh` / `direction`)

## Localisation / pointage
- `sites` (Nouvelle / Ancienne Mairie, QR, rayon GPS)
- `site_departement` (règles custom)
- `pointages.site_id`, `source=OFFLINE`, `is_visitor`, `pending_sync`

## Demandes
- Types : `PERMISSION`, `MISSION`, `CORRECTION`, `DEMISSION`, `RETRAITE`
- Statuts : `EN_COURS`, `REJETEE` + `motif_rejet`, `lue_par_admin_*`
- `demande_status_history` (traçabilité heure / statut / acteur)
- Champs horaires + `document_path` + `extra_json`

## Annonces & notifs
- Annonces : `image_url`, `when_label`, `place`, `starts_at`, `duration_hours`, `priority`
- Notifications : `categorie`, `read_at`, `play_sound`

## Paramètres admin → mobile
- `mobile_features` (modules visibles/masqués)
- `work_schedules` (horaires)
- `remote_configs` enrichi (maintenance, GPS, notifs, support…)
- `device_tokens` (préparation push FCM/APNs)
