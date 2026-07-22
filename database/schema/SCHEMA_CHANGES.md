# Évolutions du schéma SQL

Par rapport à `pointage_mairie_schema.sql` d’origine, ajouts pour couvrir l’admin et le mobile.

## Auth & sessions (Tâche 1)
- `users.last_logout_at`, `last_login_ip`, `last_user_agent`
- Table `personal_access_tokens` (Sanctum)
- Rôles `admin`, `sous_admin` (+ conservation `rh` / `direction`)

## Localisation / pointage (Tâche 1)
- `sites` (Nouvelle / Ancienne Mairie, QR, rayon GPS)
- `site_departement` (règles custom)
- `pointages.site_id`, `source=OFFLINE`, `is_visitor`, `pending_sync`

## Demandes (Tâche 1)
- Types : `PERMISSION`, `MISSION`, `CORRECTION`, `DEMISSION`, `RETRAITE`
- Statuts : `EN_COURS`, `REJETEE` + `motif_rejet`, `lue_par_admin_*`
- `demande_status_history` (traçabilité heure / statut / acteur)
- Champs horaires + `document_path` + `extra_json`

## Annonces & notifs (Tâche 1)
- Annonces : `image_url`, `when_label`, `place`, `starts_at`, `duration_hours`, `priority`
- Notifications : `categorie`, `read_at`, `play_sound`

## Paramètres admin → mobile (Tâche 1 + patch)
- `mobile_features` (modules visibles/masqués)
- `work_schedules` (horaires)
- `remote_configs` : identité app (nom, logo, org, tagline), support, maintenance, GPS,
  sécurité admin/mobile, notifs, version, démo, **config retraite**
- `device_tokens` (préparation push FCM/APNs)

## Patch gaps admin / mobile (avant Tâche 3)
- `pointages.late_minutes`, `photo_path`, `acknowledged_at` / `acknowledged_by` (retards « Traité »)
- `agents.date_fin_contrat`, `solde_conges`
- Table `planning_shifts` (Planning & Horaires admin)
- Table `agent_documents` (dossiers : PHOTO, CONTRAT, CNI, HISTORIQUE)
- `holidays.type_holiday` : + `RELIGIEUX`, `MUNICIPAL`
