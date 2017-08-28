<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for the customcert module.
 *
 * @package    mod_customcert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addcertpage'] = 'Ajouter une page au certificat';
$string['addelement'] = 'Ajouter un élément';
$string['awardedto'] = 'Décerné à';
$string['code'] = 'Code';
$string['copy'] = 'Copier';
$string['coursetimereq'] = 'Temps requis en minutes';
$string['coursetimereq_help'] = 'Entrer ici le temps minimum requis (en minutes) qu\'un étudiant doit passer dans le cours avant de pouvoir recevoir le certificat.';
$string['createtemplate'] = 'Créer un modèle';
$string['customcertreport'] = 'Rapport de certificat personnalisé';
$string['customcert:addinstance'] = 'Ajouter une nouvelle instance de certificat personnalisé';
$string['customcert:manage'] = 'Gérer un certificat personnalisé';
$string['customcert:view'] = 'Voir un certificat personnalisé';
$string['deletecertpage'] = 'Supprimer la page de certificat';
$string['deleteconfirm'] = 'Confirmation de suppression';
$string['deleteelement'] = 'Supprimer élément';
$string['deleteelementconfirm'] = 'Etes vous sûr de vouloir supprimer cet élément ?';
$string['deletepageconfirm'] = 'Etes vous sûr de vouloir supprimer cette page de certificat ?';
$string['deletetemplateconfirm'] = 'Etes vous sûr de vouloir supprimer ce modèle de certificat ?';
$string['description'] = 'Description';
$string['editcustomcert'] = 'Modifier le certificat personnalisé';
$string['editelement'] = 'Modifier l\'élément';
$string['edittemplate'] = 'Modifier un modèle';
$string['elementname'] = 'Nom de l\'élément';
$string['elementname_help'] = 'Ce sera le nom utilisé pour identifier cet élément lors de la modification d\'un certificat personnalisé. Vous pourriez par exemple avoir plusieurs images sur une page et vouloir rapidement
les identifier lors de la modification du certificat. Note : ce nom ne sera pas affiché sur le PDF.';
$string['elements'] = 'Eléments';
$string['elements_help'] = 'Il s\'agit de la liste des éléments qui seront affichés sur le certificat.

Note: Les éléménts seront affichés dans cet ordre. L\'ordre peut être changé en utilisant les flèches à côté de chaque élément.';
$string['elementwidth'] = 'Largeur';
$string['elementwidth_help'] = 'Spécifier la largeur de l\'élément- \'0\' signifiant qu\'il n\'a pas de contrainte de largeur.';
$string['font'] = 'Police';
$string['font_help'] = 'Police utilisée lors de la génération de cet élément';
$string['fontcolour'] = 'Couleur';
$string['fontcolour_help'] = 'Couleur de la police.';
$string['fontsize'] = 'Taille';
$string['fontsize_help'] = 'Taille de la police (en points)';
$string['getcustomcert'] = 'Obtenir votre certificat personnalisé';
$string['height'] = 'Hauteur';
$string['height_help'] = 'Hauteur du PDF du certificat en mm. Par exemple, une feuille de papier A4 mesure 297mm de haut alors qu\'une feuille de papier à lettre  mesure 279mm de haut.';
$string['invalidcolour'] = 'Couleur choisie incorrecte, veuillez entrer un nom de couleur HTML valide, une couleur sur 6 chiffres ou une couleur hexadécimale sur 3 chiffres.';
$string['invalidelementwidth'] = 'Veuillez entrer un nombre positif.';
$string['invalidposition'] = 'Veuillez sélectionner un nombre positif pour la position {$a}.';
$string['invalidheight'] = 'La hauteur doit être un nombre supérieur à 0.';
$string['invalidmargin'] = 'La marge doit être un nombre supérieur à 0';
$string['invalidwidth'] = 'La largeur doit être un nombre supérieur à 0';
$string['issued'] = 'Décerné(s)';
$string['landscape'] = 'Paysage';
$string['leftmargin'] = 'Marge gauche';
$string['leftmargin_help'] = 'Marge gauche du PDF du certificat (en mm)';
$string['load'] = 'Charger';
$string['loadtemplate'] = 'Charger le modèle';
$string['loadtemplatemsg'] = 'Êtes-vous sûr de vouloir charger ce modèle? Cela supprimera toutes les pages et éléments existants pour ce certificat.';
$string['managetemplates'] = 'Gérer les modèles';
$string['managetemplatesdesc'] = 'Ce lien vous amènera vers un écran ou vous pourrez gérer les modèles utilisés par customcert dans les activités dasn les cours.';
$string['modify'] = 'Modifier';
$string['modulename'] = 'Certificat personnalisé';
$string['modulenameplural'] = 'Certificats personnalisés';
$string['modulename_help'] = 'Ce module permet de générer dynamiquement des certificats en PDF.';
$string['modulename_link'] = 'Custom_certificate_module';
$string['name'] = 'Nom';
$string['nocustomcerts'] = 'Il n\'y a aucun certificat pour ce cours';
$string['noimage'] = 'Aucune image';
$string['notemplates'] = 'Aucun modèle';
$string['notissued'] = 'Pas décerné';
$string['options'] = 'Options';
$string['page'] = 'Page {$a}';
$string['pluginadministration'] = 'Administration du certificat personnalisé';
$string['pluginname'] = 'Certificat personnalisé';
$string['print'] = 'Imprimer';
$string['portrait'] = 'Portrait';
$string['rearrangeelements'] = 'Replacer les éléments';
$string['rearrangeelementsheading'] = 'Glisser déposer des éléments pour modifier leur position sur le certificat.';
$string['receiveddate'] = 'Date de réception';
$string['refpoint'] = 'Emplacement du point de référence';
$string['refpoint_help'] = 'Le point de référence est l\'emplacement à partir duquel les coordonnées x et y d\'un élément sont calculées. Il est indiqué par le signe \'+\' qui apparaît au centre ou dans les coins de l\'élément.';
$string['replacetemplate'] = 'Replacer';
$string['rightmargin'] = 'Marge droite';
$string['rightmargin_help'] = 'Marge droite du PDF du certificat en mm.';
$string['save'] = 'Enregistrer';
$string['saveandclose'] = 'Enregistrer et fermer';
$string['saveandcontinue'] = 'Enregistrer et continuer';
$string['savechangespreview'] = 'Enregistrer les modifications et prévisualiser';
$string['savetemplate'] = 'Enregistrer le modèle';
$string['setprotection'] = 'Activer protection';
$string['setprotection_help'] = 'Choisissez les actions que vous souhaitez empêcher les utilisateurs d\'effectuer sur ce certificat.';
$string['summaryofissue'] = 'Bilan des attributions';
$string['templatename'] = 'Nom du modèle';
$string['templatenameexists'] = 'Ce nom de modèle est déjà pris, veuillez en choisir un autre.';
$string['topcenter'] = 'Centre';
$string['topleft'] = 'Coin supérieur gauche';
$string['topright'] = 'Coin supérieur droit';
$string['type'] = 'Type';
$string['uploadimage'] = 'Téléverser une image';
$string['uploadimagedesc'] = 'Ce lien vous amènera à un nouvel écran où vous pourrez téléverser des images. Les images téléversées suivant cette méthode seront disponibles sur tout le site pour tous les utilisateurs habilités à créer un certificat personnalisé.';
$string['viewcustomcertissues'] = 'Voir le(s) {$a} certificat(s) décerné(s)';
$string['width'] = 'Largeur';
$string['width_help'] = 'Largeur du PDF du certificat en mm. Par exemple, une feuille de papier A4 mesure 210mm de large alors qu\'une feuille de papier à lettre  mesure 216mm de large.';
