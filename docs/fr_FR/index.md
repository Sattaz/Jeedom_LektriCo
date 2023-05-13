Description 
===

Le plugin LektriCo permet de piloter un chargeur de voiture électrique LektriCo.

En application courante, il permet par exemple de démarrer ou arrêter une charge manuellement mais encore d'ajuster automatiquement la puissance de charge en fonction de la puissance réelle générée par une installation photovoltaïque depuis un scénario dans Jeedom.

Le chargeur est disponible ici:
[Lien vers le chargeur LektriCo](https://lektri.co/)


Remarques:

- La borne existe en monophasé et en triphasé.

- La borne LektriCo peut varier l’intensité de charge de 6 a 32 ampères.

Configuration du plugin 
===

La configuration du plugin est très simple.
Une fois installé, il suffit de créer un nouvel équipement et de le configurer de la manière suivantes:

![LektriCo](https://sattaz.github.io/Jeedom_LektriCo/pictures/LektriCo_1.jpg)

Comme pour chaque plugin Jeedom, il faudra indiquer le 'Nom de l'équipement', un 'Objet parent' et une 'Catégorie'.
Ne pas oublier de cocher les cases 'Activer' et 'Visible'.

Puis viennent aussi quelques paramètres dédiés aux spécifications du chargeur et l'installation électrique:

-   IP de la borne : veuillez renseigner l'adresse IP de l'interface web du chargeur LektriCo.

-   Intensité de charge minimum (A) : veuillez sélectionner l'ampérage minimum que le chargeur doit délivrer (en ampères)

-   Intensité de charge maximum (A) : veuillez sélectionner l'ampérage maximum que le chargeur ne doit pas dépasser, ceci en fonction de votre installation/abonnement électrique (en ampères)

-   Commande lecture mode tarification : vous pouvez renseigner une commande de type information qui indique le mode de tarification en cours.

-   Mode tarification chargement auto. : vous pouvez renseigner un texte ou une valeur numérique pour désigner à quoi correspond le mode de tarification obtenu précédement par la commande de 'lecture mode tarification' et qui determinera quand démarrer une charge automatiquement lors du passage en heure creuse par exemple (HC, etc...)

-> Veuillez dès à présent appuyer sur le bouton 'Sauvegarder' afin d'enregistrer la configuration.

-> Cette action va automatiquement créer les commandes de l'équipement.

Commandes de l'équipement 
===

Comme énoncé dans le précédent chapitre, les commandes de l'équipement sont automatiquement crées dès lors que la configuration est sauvegardée.

![LektriCo](https://sattaz.github.io/Jeedom_LektriCo/pictures/LektriCo_2.jpg)

Informations pratiques:

-> Les commandes 'Perso. Texte', 'Perso. Num.' et 'Perso. Bin.' peuvent etre utilisée comme bon vous semble afin, par exemple, d'afficher des informations que vous obtiendrez depuis un scénario et les afficher sur la tuile du plugin.

Le widget 
===

Le widget arrive comme montré sur la photo ci-après et le curseur permettant le réglage de la puissance du chargeur est calibrée (min/max) par l'ampérage de charge maximum indiquée dans la configuration de l'équipement.

![LektriCo](https://sattaz.github.io/Jeedom_LektriCo/pictures/LektriCo_3.jpg)

Libre à vous de modifier le widget afin de l'adapter à votre style de présentation.

Informations pratiques:

-> Les boutons 'ON' et 'OFF' permettent de démarrer ou arrêter une charge.

-> Les boutons 'Man.' et 'Auto.' n'ont aucun effet direct avec le chargeur, ils permettent simplement de changer l'information 'Mode' du plugin en 'Manuel' ou 'Automatique'.
   Vous pouvez donc utiliser un scenario depuis Jeedom afin d'interragir de facon différente en fonction du 'Mode' du plugin:

* En 'Manuel', le scénario pourra par exemple ignorer tout automatisme et donc laisser l'utilisateur démarrer ou arrêter une charge avec les boutons 'ON' et 'OFF' manuellement.

* En 'Automatique', le scénario pourra par exemple utiliser une information de puissance produite par une installation photovoltaïque et interragir avec le plugin en ajustant la consigne d'ampérage de charge, démarrer une session de charge en executant la commande 'ON', arrêter une session de charge en executant la commande 'OFF' ou encore faire tout cela pendant les heures creuses en ajustant l'ampérage de charge au maximum.

Bref, les possibilités sont multiples.

Autres informations 
===

* Le plugin rafraîchi les données toutes les minutes.

* Vous pouvez créer plusieurs équipements pour gérer différents chargeurs.
