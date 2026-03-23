<?php
require_once '../includes/session.php';
require_once '../includes/donnees.php';
exiger_role('restaurateur');

$message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $commande_id = (int)($_POST['commande_id'] ?? 0);
    $commande    = get_commande_par_id($commande_id);

    if ($commande) {
        if ($action === 'lancer' && $commande['statut'] === 'en_attente') {
            $commande['statut'] = 'en_preparation';
            sauvegarder_commande($commande);
            $message = "Commande #{$commande_id} lancée en préparation.";
        } elseif ($action === 'pret' && $commande['statut'] === 'en_preparation') {
            if ($commande['type'] === 'livraison') {
                // Assigner un livreur si fourni
                $livreur_id = (int)($_POST['livreur_id'] ?? 0);
                if ($livreur_id > 0) {
                    $commande['livreur_id'] = $livreur_id;
                    $commande['statut']     = 'en_livraison';
                    sauvegarder_commande($commande);
                    $livreur = get_utilisateur_par_id($livreur_id);
                    $message = "Commande #{$commande_id} assignée à {$livreur['prenom']} {$livreur['nom']}.";
                } else {
                    $message = "Veuillez sélectionner un livreur.";
                }
            } else {
                // Sur place ou à emporter : directement livrée
                $commande['statut'] = 'livree';
                $commande['date_livraison_effective'] = date('Y-m-d H:i:s');
                sauvegarder_commande($commande);
                $message = "Commande #{$commande_id} marquée comme servie/remise.";
            }
        }
    }
}

// Récupérer les commandes
$en_attente     = array_values(get_commandes_par_statut('en_attente'));
$en_preparation = array_values(get_commandes_par_statut('en_preparation'));
$en_livraison   = array_values(get_commandes_par_statut('en_livraison'));
$livreurs_dispo = array_values(get_livreurs_disponibles());
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Interface Cuisine - Pizza Nova</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php $base = '../';
require_once '../includes/nav.php'; ?>
<main class="cuisine container">
    <h2>Commandes en cours 🍕</h2>

    <?php if ($message): ?>
        <p class="message-succes"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- Kanban -->
    <div class="kanban">

        <!-- Colonne En attente -->
        <div class="kanban-col">
            <h3 class="kanban-titre attente">⏳ En attente (<?= count($en_attente) ?>)</h3>
            <?php foreach ($en_attente as $cmd):
                $client = get_utilisateur_par_id($cmd['client_id']);
            ?>
            <article class="commande apreparer">
                <h4>Commande #<?= $cmd['id'] ?></h4>
                <p><strong>Client :</strong> <?= htmlspecialchars($client ? $client['prenom'].' '.$client['nom'] : '?') ?></p>
                <p><strong>Type :</strong> <?= $cmd['type'] === 'livraison' ? '🛵 Livraison' : '🏠 Sur place' ?></p>
                <p><strong>Heure souhaitée :</strong> <?= $cmd['date_livraison_souhaitee'] ? date('H:i', strtotime($cmd['date_livraison_souhaitee'])) : 'Immédiat' ?></p>
                <ul>
                    <?php foreach ($cmd['articles'] as $art): ?>
                        <li><?= $art['quantite'] ?>x <?= htmlspecialchars($art['nom']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Total :</strong> <?= number_format($cmd['total'], 2) ?> €</p>
                <form method="post" action="restaurateur.php">
                    <input type="hidden" name="commande_id" value="<?= $cmd['id'] ?>">
                    <input type="hidden" name="action" value="lancer">
                    <button type="submit" class="btn-main btn-preparer">▶ Lancer la préparation</button>
                </form>
            </article>
            <?php endforeach; ?>
            <?php if (empty($en_attente)): ?>
                <p class="vide">Aucune commande en attente.</p>
            <?php endif; ?>
        </div>

        <!-- Colonne En préparation -->
        <div class="kanban-col">
            <h3 class="kanban-titre preparation">👨‍🍳 En préparation (<?= count($en_preparation) ?>)</h3>
            <?php foreach ($en_preparation as $cmd):
                $client = get_utilisateur_par_id($cmd['client_id']);
            ?>
            <article class="commande en-prep">
                <h4>Commande #<?= $cmd['id'] ?></h4>
                <p><strong>Client :</strong> <?= htmlspecialchars($client ? $client['prenom'].' '.$client['nom'] : '?') ?></p>
                <p><strong>Type :</strong> <?= $cmd['type'] === 'livraison' ? '🛵 Livraison' : '🏠 Sur place' ?></p>
                <ul>
                    <?php foreach ($cmd['articles'] as $art): ?>
                        <li><?= $art['quantite'] ?>x <?= htmlspecialchars($art['nom']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" action="restaurateur.php">
                    <input type="hidden" name="commande_id" value="<?= $cmd['id'] ?>">
                    <input type="hidden" name="action" value="pret">
                    <?php if ($cmd['type'] === 'livraison'): ?>
                        <label>Assigner un livreur :</label>
                        <select name="livreur_id" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach ($livreurs_dispo as $l): ?>
                                <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['prenom'].' '.$l['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="submit" class="btn-main" style="background:#27ae60;">
                        <?= $cmd['type'] === 'livraison' ? '🛵 Envoyer en livraison' : '✅ Marquer comme servi' ?>
                    </button>
                </form>
            </article>
            <?php endforeach; ?>
            <?php if (empty($en_preparation)): ?>
                <p class="vide">Rien en préparation.</p>
            <?php endif; ?>
        </div>

        <!-- Colonne En livraison -->
        <div class="kanban-col">
            <h3 class="kanban-titre livraison-col">🛵 En livraison (<?= count($en_livraison) ?>)</h3>
            <?php foreach ($en_livraison as $cmd):
                $client  = get_utilisateur_par_id($cmd['client_id']);
                $livreur = get_utilisateur_par_id($cmd['livreur_id']);
            ?>
            <article class="commande livraison">
                <h4>Commande #<?= $cmd['id'] ?></h4>
                <p><strong>Client :</strong> <?= htmlspecialchars($client ? $client['prenom'].' '.$client['nom'] : '?') ?></p>
                <p><strong>Adresse :</strong> <?= htmlspecialchars($cmd['adresse_livraison']) ?></p>
                <p><strong>Livreur :</strong> <?= $livreur ? htmlspecialchars($livreur['prenom'].' '.$livreur['nom']) : '?' ?></p>
                <p><small>Commande passée à <?= date('H:i', strtotime($cmd['date_commande'])) ?></small></p>
            </article>
            <?php endforeach; ?>
            <?php if (empty($en_livraison)): ?>
                <p class="vide">Aucune livraison en cours.</p>
            <?php endif; ?>
        </div>

    </div>
</main>
<footer>
    <p>&copy; 2025-2026 Projet Pizza Nova -préING2- Ibrahim, Ikram &amp; Matthieu</p>
</footer>
</body>
</html>
