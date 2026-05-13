<?php
require_once(__DIR__ . '/includes/session.php');
require_once(__DIR__ . '/includes/donnees.php');
require_once(__DIR__ . '/includes/getapikey.php');

// Récupérer l'utilisateur connecté
$u = get_utilisateur_connecte();

// Si plus de session (CYBank a redirigé et la session a expiré)
// On affiche juste un message générique
if (!$u) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head><meta charset="UTF-8"><title>Confirmation - Pizza Nova</title></head>
    <body>
    <main class="container">
        <div class="form-container" style="text-align:center;">
            <h1>✅ Paiement reçu !</h1>
            <p>Votre commande a été enregistrée.</p>
            <a href="connexion.php" class="btn-main">Se connecter pour voir ma commande</a>
        </div>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$vendeur      = "MI-3_I";
$api_key      = getAPIKey($vendeur);

// Paramètres renvoyés par CYBank en GET
$transaction  = $_GET['transaction'] ?? '';
$montant      = $_GET['montant']     ?? '';
$statut       = $_GET['status']      ?? '';
$control_recu = $_GET['control']     ?? '';

// Recalculer le hash de vérification
$control_verif = md5($api_key . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $statut . "#");

$message              = '';
$commande_id          = null;
$paiement_valide      = false;

if ($statut === 'accepted' && $control_recu === $control_verif) {

    // Récupérer les infos temporaires stockées avant la redirection CYBank
    $temp        = $_SESSION['temp_commande'] ?? [];
    $type_choisi = $temp['type_commande']     ?? 'livraison';
    $date_souh   = $temp['date_souhaitee']    ?? null;

    // Construire l'adresse selon le type
    if ($type_choisi === 'livraison') {
        $adresse_finale = $u['adresse'] ?? 'Adresse non renseignée';
        $details_finaux = $u['details'] ?? '';
    } elseif ($type_choisi === 'emporter') {
        $adresse_finale = 'À emporter';
        $details_finaux = '';
    } else {
        $adresse_finale = 'Sur place';
        $details_finaux = '';
    }

    // Construire les articles depuis la session
    $articles = [];
    foreach ($_SESSION['panier'] ?? [] as $item) {
        $articles[] = [
            'type'          => $item['type'],
            'id'            => $item['id'],
            'quantite'      => $item['quantite'],
            'nom'           => $item['nom'],
            'prix_unitaire' => $item['prix'],
        ];
    }

    // Créer la commande complète dans commandes.json
    $nouvelle_commande = [
        'client_id'                => $u['id'],
        'articles'                 => $articles,
        'total'                    => (float)$montant,
        'adresse_livraison'        => $adresse_finale,
        'details_livraison'        => $details_finaux,
        'telephone_client'         => $u['telephone'] ?? '',
        'type'                     => $type_choisi,
        'statut'                   => 'en_attente',
        'livreur_id'               => null,
        'date_commande'            => date('Y-m-d H:i:s'),
        'date_livraison_souhaitee' => $date_souh ?: null,
        'date_livraison_effective' => null,
        'paiement_statut'          => 'paye',
        'note_produits'            => null,
        'note_livraison'           => null,
        'commentaire'              => null,
    ];

    $commande_id = ajouter_commande($nouvelle_commande);

    // Ajouter des points fidélité (1 point par euro)
    $u_frais = get_utilisateur_par_id($u['id']);
    if ($u_frais) {
        $u_frais['points_fidelite'] = ($u_frais['points_fidelite'] ?? 0) + (int)$montant;
        sauvegarder_utilisateur($u_frais);
    }

    // Nettoyer la session
    $_SESSION['panier']        = [];
    $_SESSION['temp_commande'] = [];

    $paiement_valide = true;
    $message         = "Commande #{$commande_id} enregistrée avec succès !";

} else {
    $message = "Le paiement a été refusé ou annulé. Aucune commande n'a été créée.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation de commande - Pizza Nova</title>
</head>
<body>
<?php $base = ''; require_once(__DIR__ . '/includes/nav.php'); ?>

<main class="container">
    <div class="form-container" style="text-align:center;">

        <?php if ($paiement_valide): ?>
            <h1>✅ Merci pour votre commande !</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <p style="margin-top:10px;">
                Elle est maintenant <strong>en attente de préparation</strong>
                par notre équipe cuisine.
            </p>
            <br>
            <a href="profil.php" class="btn-main" style="margin-right:10px;">
                📋 Suivre ma commande
            </a>
            <a href="carte.php" class="btn-main">
                🍕 Commander encore
            </a>

        <?php else: ?>
            <h1>❌ Paiement refusé</h1>
            <p><?= htmlspecialchars($message) ?></p>
            <br>
            <a href="panier.php" class="btn-main">
                ← Retour au panier
            </a>
        <?php endif; ?>

    </div>
</main>

<footer>
    <p>&copy; 2025-2026 Projet Pizza Nova -préING2- Ibrahim, Ikram &amp; Matthieu</p>
</footer>
</body>
</html>
