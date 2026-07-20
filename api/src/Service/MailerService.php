<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Entity\Utilisateur;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Service centralisé pour l'envoi des e-mails transactionnels.
 *
 * Les templates Twig se trouvent dans templates/emails/.
 */
class MailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string          $fromAddress,
        private readonly string          $fromName,
        private readonly string          $frontendUrl,
        private readonly string          $contactEmail,
    ) {
    }

    /**
     * E-mail de bienvenue envoyé à la création du compte utilisateur.
     */
    public function envoyerBienvenue(Utilisateur $utilisateur): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($utilisateur->getEmail(), $utilisateur->getPrenom() . ' ' . $utilisateur->getNom()))
            ->subject('Bienvenue chez Vite & Gourmand !')
            ->htmlTemplate('emails/bienvenue.html.twig')
            ->context([
                'prenom'      => $utilisateur->getPrenom(),
                'frontendUrl' => $this->frontendUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * E-mail contenant le lien de réinitialisation du mot de passe.
     * IMPORTANT : le token en clair est envoyé UNE seule fois par mail ;
     * en base, seul son hash SHA-256 est conservé.
     */
    public function envoyerReinitialisationMotDePasse(Utilisateur $utilisateur, string $tokenClair): void
    {
        $lienReset = sprintf('%s/reinitialiser?token=%s', $this->frontendUrl, $tokenClair);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($utilisateur->getEmail(), $utilisateur->getPrenom()))
            ->subject('Réinitialisation de votre mot de passe')
            ->htmlTemplate('emails/reinitialisation_mdp.html.twig')
            ->context([
                'prenom'      => $utilisateur->getPrenom(),
                'lien'        => $lienReset,
                'validiteMin' => 60,   // 1 h de validité
            ]);

        $this->mailer->send($email);
    }

    /**
     * Notification à un nouvel employé qu'un compte a été créé pour lui.
     * Le mot de passe n'est PAS transmis par e-mail : il doit contacter l'admin.
     */
    public function envoyerNotificationCompteEmploye(Utilisateur $employe): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($employe->getEmail(), $employe->getPrenom()))
            ->subject('Votre compte employé Vite & Gourmand a été créé')
            ->htmlTemplate('emails/compte_employe_cree.html.twig')
            ->context([
                'prenom'      => $employe->getPrenom(),
                'frontendUrl' => $this->frontendUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie le message du formulaire de contact à l'entreprise.
     */
    public function envoyerMessageContact(string $titre, string $emailExpediteur, string $description): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($this->contactEmail))
            ->replyTo(new Address($emailExpediteur))
            ->subject('[Contact] ' . $titre)
            ->htmlTemplate('emails/contact.html.twig')
            ->context([
                'titre'           => $titre,
                'emailExpediteur' => $emailExpediteur,
                'description'     => $description,
            ]);

        $this->mailer->send($email);
    }

    // ═══════════════════════════════════════════════════════════
    // COMMANDES — Notifications transactionnelles
    // ═══════════════════════════════════════════════════════════

    /**
     * Confirmation à la création d'une commande.
     * Sujet : "Après avoir commandé un menu, le visiteur va recevoir un mail
     *          lui confirmant la commande."
     */
    public function envoyerConfirmationCommande(Commande $commande): void
    {
        $u = $commande->getUtilisateur();
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($u->getEmail(), $u->getPrenom() . ' ' . $u->getNom()))
            ->subject('Confirmation de votre commande ' . $commande->getNumeroCommande())
            ->htmlTemplate('emails/commande_confirmation.html.twig')
            ->context([
                'commande' => $commande,
                'prenom'   => $u->getPrenom(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * Notification de changement de statut au client.
     * Ex: acceptée, terminée, annulée…
     */
    public function envoyerChangementStatutCommande(Commande $commande): void
    {
        $u = $commande->getUtilisateur();
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($u->getEmail(), $u->getPrenom() . ' ' . $u->getNom()))
            ->subject(sprintf(
                'Votre commande %s : %s',
                $commande->getNumeroCommande(),
                $commande->getStatut()->label()
            ))
            ->htmlTemplate('emails/commande_statut.html.twig')
            ->context([
                'commande'    => $commande,
                'prenom'      => $u->getPrenom(),
                'frontendUrl' => $this->frontendUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Notification "matériel à restituer" — statut ATTENTE_MATERIEL.
     * Sujet : "dès que ce statut est atteint, le client reçoit un mail lui
     *          notifiant que si sous 10 jours ouvré, le matériel n'est pas
     *          restitué, alors, il devra s'acquitter de 600 euros de frais"
     */
    public function envoyerNotificationRetourMateriel(Commande $commande): void
    {
        $u = $commande->getUtilisateur();
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($u->getEmail(), $u->getPrenom() . ' ' . $u->getNom()))
            ->subject('Restitution du matériel prêté — commande ' . $commande->getNumeroCommande())
            ->htmlTemplate('emails/commande_retour_materiel.html.twig')
            ->context([
                'commande'      => $commande,
                'prenom'        => $u->getPrenom(),
                'delaiJours'    => 10,
                'penaliteEuros' => 600,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Invitation à laisser un avis quand la commande est terminée.
     * Sujet : "Quand la commande est 'terminée', alors, l'utilisateur est
     *          notifié par mail qu'il peut se connecter à son compte pour
     *          donner son avis depuis la commande."
     */
    public function envoyerInvitationAvis(Commande $commande): void
    {
        $u = $commande->getUtilisateur();
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($u->getEmail(), $u->getPrenom() . ' ' . $u->getNom()))
            ->subject('Comment s\'est passé votre événement ?')
            ->htmlTemplate('emails/commande_invitation_avis.html.twig')
            ->context([
                'commande'    => $commande,
                'prenom'      => $u->getPrenom(),
                'frontendUrl' => $this->frontendUrl,
            ]);

        $this->mailer->send($email);
    }
}
