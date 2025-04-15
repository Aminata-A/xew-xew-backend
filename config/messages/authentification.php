<?php

return [
    'errors' => [
        'token' => [
            'missing' => 'Le token est manquant. Veuillez vérifier votre email pour obtenir un token valide.',
            'expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
            'invalid' => 'Token invalide. Veuillez vérifier votre email pour un nouveau token.',
            'not_authenticated' => 'Vous devez être authentifié pour accéder à cette ressource.',
            'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
            'session_invalid' => 'Session invalide. Veuillez vous reconnecter.',
            'generation_failed' => 'La génération du token a échoué.'
        ],
        'validation' => [
            'name' => [
                'required' => 'Le nom est requis pour créer un compte.',
                'max' => 'Le nom ne peut pas dépasser 100 caractères.'
            ],
            'email' => [
                'required' => 'L\'adresse email est requise.',
                'invalid' => 'L\'adresse email n\'est pas valide.',
                'exists' => 'Cette adresse email n\'est pas enregistrée.',
                'already_exists' => 'Cette adresse email est déjà utilisée.'
            ],
            'password' => [
                'required' => 'Un mot de passe est requis pour votre sécurité.',
                'min' => 'Le mot de passe doit contenir au moins 6 caractères.',
                'mismatch' => 'Les mots de passe ne correspondent pas.',
                'invalid' => 'Le mot de passe est incorrect.',
                'confirmation_required' => 'La confirmation du mot de passe est requise.',
                'confirmation_mismatch' => 'La confirmation du mot de passe ne correspond pas.'
            ],
            'phone' => [
                'required' => 'Le numéro de téléphone est requis.',
                'max' => 'Le numéro ne peut pas dépasser 20 caractères.',
                'numeric' => 'Le numéro de téléphone ne doit contenir que des chiffres.'
            ],
            'role' => [
                'required' => 'Veuillez sélectionner un rôle (organisateur ou participant).',
                'invalid' => 'Rôle invalide. Choisissez entre organisateur et participant.'
            ],
            'verification_code' => [
                'required' => 'Le code de vérification est requis.',
                'invalid' => 'Le code de vérification est invalide.',
                'expired' => 'Le code de vérification a expiré. Veuillez demander un nouveau code.',
                'not_found' => 'Aucun code de vérification trouvé pour cet email.'
            ],
            'photo' => [
                'invalid_type' => 'Le format de la photo n\'est pas supporté. Formats acceptés : jpeg, png, jpg, gif, svg.',
                'max_size' => 'La taille de la photo ne doit pas dépasser 2MB.'
            ]
        ],
        'account' => [
            'not_activated' => 'Ce compte n\'a pas encore été activé.',
            'inactive' => 'Votre compte est désactivé. Veuillez contacter l\'administrateur.',
            'already_verified' => 'Ce compte est déjà vérifié.'
        ],
        'email' => [
            'verification_failed' => 'L\'envoi de l\'email de vérification a échoué.',
            'already_verified' => 'Cette adresse email a déjà été vérifiée.',
            'not_found' => 'Aucun utilisateur trouvé avec cette adresse email.'
        ],
        'general' => [
            'validation_errors' => 'Veuillez corriger les erreurs suivantes :',
            'unexpected' => 'Une erreur inattendue s\'est produite. Veuillez réessayer.'
        ]
    ],
    'success' => [
        'registration' => 'Inscription réussie ! Bienvenue sur notre plateforme.',
        'login' => 'Connexion réussie ! Bienvenue de retour.',
        'profile_update' => 'Votre profil a été mis à jour avec succès.',
        'logout' => 'Vous avez été déconnecté avec succès.',
        'email_verification_sent' => 'Un code de vérification a été envoyé à votre adresse email.',
        'email_verified' => 'Votre email a été vérifié avec succès.',
        'code_verified' => 'Code vérifié avec succès.'
    ],
    'info' => [
        'profile_retrieved' => 'Profil récupéré avec succès.',
        'verification_success' => 'Votre email a été vérifié avec succès.',
        'verification_pending' => 'Veuillez vérifier votre email pour compléter l\'inscription.',
        'code_sent' => 'Un nouveau code de vérification a été envoyé à votre email.'
    ]
];
