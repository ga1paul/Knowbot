<?php
// ═══════════════════════════════════════════════════════════════════
// KnowBot — Chatbot Éducatif Haïtien
// Copyright (C) 2026 [Non ou]
// Licensed under GNU GPL v3.0
// https://www.gnu.org/licenses/gpl-3.0.html
//  chat.php — Moteur IA Hybride : Analyse sémantique + Génération NLP
//
//  Architecture :
//  1. Détection d'intention avancée (domaine + type + entités)
//  2. Extraction de réponse directe si question factuelle précise
//  3. Chaîne de Markov ordre 3 avec lissage de Backoff
//  4. Enrichissement sémantique par synonymes
//  5. Templates structurés par domaine et type
//  6. Post-traitement + cohérence textuelle
//  7. Sauvegarde conversation en BDD
// ═══════════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$DB = ['host'=>'localhost','name'=>'knowbot_db','user'=>'root','pass'=>''];

function getDB(array $cfg): ?PDO {
    try {
        return new PDO(
            "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4",
            $cfg['user'], $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) { return null; }
}

// ────────────────────────────────────────────────────────────
//  1. ENTRÉE
// ────────────────────────────────────────────────────────────
$message = trim($_POST['message'] ?? '');
if (empty($message)) { echo json_encode(['error'=>'Message vide.']); exit; }
$msgLow = mb_strtolower($message, 'UTF-8');

// ────────────────────────────────────────────────────────────
//  2. BASE DE CONNAISSANCES FACTUELLES (réponses directes)
//     Pour les questions précises → réponse exacte sans Markov
// ────────────────────────────────────────────────────────────
$FAITS = [
    // Haïti — dates et chiffres
    ['trigger'=>['indépendance haïti','date indépendance','quand haiti','1804'],
     'reponse'=>"Haïti a proclamé son indépendance le **1er janvier 1804** à Gonaïves, par **Jean-Jacques Dessalines**. C'est la première République noire libre du monde et la première nation à abolir l'esclavage de façon permanente."],
    ['trigger'=>['capitale haïti','capitale de haïti','chef-lieu haïti'],
     'reponse'=>"La capitale d'Haïti est **Port-au-Prince**, située dans le département de l'Ouest. C'est la plus grande ville du pays et son principal centre économique, politique et culturel."],
    ['trigger'=>['combien département','10 département','nombre département haïti'],
     'reponse'=>"Haïti compte **10 départements** :\n1. Ouest (Port-au-Prince)\n2. Nord (Cap-Haïtien)\n3. Sud (Les Cayes)\n4. Artibonite (Gonaïves)\n5. Centre (Hinche)\n6. Nord-Est (Fort-Liberté)\n7. Nord-Ouest (Port-de-Paix)\n8. Sud-Est (Jacmel)\n9. Nippes (Miragoâne)\n10. Grand'Anse (Jérémie)"],
    ['trigger'=>['population haïti','habitants haïti','combien habitant'],
     'reponse'=>"La population d'Haïti est estimée à environ **11 millions d'habitants**, ce qui en fait le pays le plus densément peuplé des Caraïbes."],
    ['trigger'=>['bois caïman','cérémonie bois caïman'],
     'reponse'=>"La cérémonie du **Bois Caïman** s'est tenue en **août 1791** sous la direction de **Dutty Boukman**. C'est l'événement fondateur de la Révolution haïtienne, où des esclaves se sont réunis pour préparer le soulèvement général contre le système colonial."],
    ['trigger'=>['vertières','bataille vertières'],
     'reponse'=>"La **bataille de Vertières** a eu lieu le **18 novembre 1803** près de Cap-Haïtien. Commandées par **Jean-Jacques Dessalines** et **François Capois (Capois-la-Mort)**, les forces haïtiennes ont vaincu l'armée française du général Rochambeau. Cette victoire décisive a ouvert la voie à l'indépendance."],
    // Mathématiques
    ['trigger'=>['pythagore','théorème pythagore','c² = a² + b²'],
     'reponse'=>"Le **théorème de Pythagore** stipule que dans un triangle rectangle, le carré de l'hypoténuse (côté opposé à l'angle droit) est égal à la somme des carrés des deux autres côtés :\n\n**c² = a² + b²**\n\nExemple : si a = 3 et b = 4, alors c = √(9 + 16) = √25 = **5**."],
    ['trigger'=>['discriminant','delta équation','b² - 4ac'],
     'reponse'=>"Le **discriminant** d'une équation du second degré ax² + bx + c = 0 est :\n\n**Δ = b² - 4ac**\n\n• Si Δ > 0 → 2 solutions réelles\n• Si Δ = 0 → 1 solution (racine double)\n• Si Δ < 0 → aucune solution réelle"],
    // Sciences
    ['trigger'=>['newton','loi newton','f = ma','première loi','deuxième loi','troisième loi'],
     'reponse'=>"Les **3 lois de Newton** :\n\n**1ère loi (Inertie) :** Un corps reste au repos ou en mouvement rectiligne uniforme si aucune force ne lui est appliquée.\n\n**2ème loi :** F = m × a — la force est proportionnelle à la masse et à l'accélération.\n\n**3ème loi (Action-Réaction) :** À toute action correspond une réaction égale et opposée."],
    ['trigger'=>['photosynthèse','comment fonctionne photosynthèse'],
     'reponse'=>"La **photosynthèse** est le processus par lequel les plantes produisent leur nourriture :\n\n**6CO₂ + 6H₂O + lumière → C₆H₁₂O₆ + 6O₂**\n\nElle se déroule dans les **chloroplastes** grâce à la chlorophylle. Ce processus est la base de toute la chaîne alimentaire terrestre."],
    // Économie
    ['trigger'=>['pib','produit intérieur brut','croissance économique'],
     'reponse'=>"Le **PIB (Produit Intérieur Brut)** mesure la valeur totale des biens et services produits dans un pays sur une période donnée. C'est le principal indicateur de la santé économique d'une nation. Un PIB en hausse indique une croissance économique, en baisse une récession."],
    ['trigger'=>['inflation','hausse prix','dévaluation gourde'],
     'reponse'=>"L'**inflation** est la hausse généralisée et durable des prix. Elle diminue le pouvoir d'achat des ménages. En Haïti, l'inflation est influencée par la dévaluation de la gourde, la dépendance aux importations et les troubles socio-politiques."],
    // Politique
    ['trigger'=>['constitution haïti','système politique haïti','démocratie haïti'],
     'reponse'=>"Haïti est une **République** avec un système démocratique basé sur la séparation des trois pouvoirs :\n- **Exécutif** : Président et Premier Ministre\n- **Législatif** : Parlement (Sénat + Chambre des Députés)\n- **Judiciaire** : Tribunaux et Cour de Cassation\n\nLa Constitution actuelle date de **1987**."],
];

// ────────────────────────────────────────────────────────────
//  3. CORPUS ENRICHI PAR DOMAINE
// ────────────────────────────────────────────────────────────
$DOMAINES = [

'mathematiques' => [
  'keywords' => ['fraction','équation','algèbre','calcul','nombre','mathématique',
                  'addition','soustraction','multiplication','division','géométrie',
                  'triangle','cercle','polynôme','vecteur','intégrale','dérivée',
                  'logarithme','probabilité','statistique','ensemble','limite',
                  'racine','exposant','factoriel','matrice','déterminant','puissance',
                  'ratio','proportion','pourcentage','angle','périmètre','aire','volume'],
  'corpus' => [
    "Les mathématiques sont la science qui étudie les structures abstraites et les relations quantitatives entre grandeurs. Une fraction représente un nombre rationnel exprimé sous forme de rapport entre deux entiers appelés numérateur et dénominateur. Pour additionner deux fractions il faut trouver leur dénominateur commun qui est le plus petit commun multiple des deux dénominateurs. On ajuste ensuite chaque numérateur en conséquence et on additionne les numérateurs obtenus. La simplification d'une fraction consiste à diviser le numérateur et le dénominateur par leur plus grand commun diviseur pour obtenir une fraction irréductible.",
    "Une équation du second degré est une équation de la forme ax² plus bx plus c égale zéro où a b et c sont des coefficients réels avec a non nul. Le discriminant delta est défini par la formule b au carré moins quatre fois a fois c. Lorsque le discriminant est strictement positif l'équation admet deux solutions réelles distinctes données par les formules de résolution. Lorsque le discriminant est nul l'équation admet une unique solution réelle appelée racine double égale à moins b divisé par deux fois a.",
    "La géométrie euclidienne étudie les figures et les formes dans le plan et dans l'espace. Le théorème de Pythagore affirme que dans tout triangle rectangle le carré de la longueur de l'hypoténuse est égal à la somme des carrés des longueurs des deux autres côtés. La somme des angles intérieurs d'un triangle est toujours égale à cent quatre-vingts degrés dans le plan euclidien. L'aire d'un cercle de rayon r est égale à pi multiplié par r au carré et son périmètre est égal à deux pi fois r.",
    "Les probabilités mesurent la chance qu'un événement se réalise sur une échelle allant de zéro à un. Un événement certain a une probabilité de un et un événement impossible a une probabilité de zéro. La loi des grands nombres affirme que plus on répète une expérience aléatoire plus la fréquence observée se rapproche de la probabilité théorique. La moyenne arithmétique d'une série de données est la somme de toutes les valeurs divisée par le nombre de valeurs.",
    "Les pourcentages sont des fractions dont le dénominateur est égal à cent et ils permettent d'exprimer facilement des proportions et des ratios. Pour calculer un pourcentage d'une quantité on multiplie cette quantité par le taux et on divise par cent. Les ratios et proportions sont utilisés dans de nombreux domaines pratiques comme la cuisine l'architecture et les sciences économiques. La règle de trois est une méthode simple pour résoudre des problèmes de proportionnalité directe ou inverse.",
    "Les fonctions mathématiques établissent une correspondance entre des ensembles et elles sont fondamentales en analyse mathématique. La dérivée d'une fonction mesure le taux de variation instantané de cette fonction en un point. L'intégrale d'une fonction représente l'aire algébrique entre la courbe et l'axe des abscisses sur un intervalle. Les suites numériques arithmétiques ont une raison constante entre deux termes consécutifs et les suites géométriques ont un quotient constant.",
  ],
  'intro' => [
    "Voici l'explication mathématique :",
    "Pour bien comprendre ce concept mathématique :",
    "Analysons ce problème mathématique étape par étape :",
  ]
],

'sciences' => [
  'keywords' => ['newton','physique','force','gravité','mouvement','énergie','vitesse',
                  'biologie','chimie','cellule','atome','molécule','réaction','électron',
                  'photosynthèse','gène','adn','thermodynamique','électricité','onde','lumière',
                  'pression','température','masse','accélération','inertie','proton','neutron',
                  'osmose','diffusion','enzyme','protéine','chromosome','mutation','évolution',
                  'écosystème','chaîne alimentaire','biodiversité','écologie','micro-organisme'],
  'corpus' => [
    "La physique est la science fondamentale qui étudie les lois régissant la nature et l'univers. La première loi de Newton stipule qu'un corps soumis à des forces équilibrées reste soit au repos soit en mouvement rectiligne uniforme. La deuxième loi de Newton établit que la résultante des forces appliquées à un corps est égale au produit de sa masse par son accélération. La troisième loi de Newton affirme que toute action entraîne une réaction égale et opposée ce qui explique le recul d'une arme à feu ou la propulsion d'une fusée.",
    "La cellule est l'unité structurale et fonctionnelle de tout être vivant. Les cellules procaryotes comme les bactéries ne possèdent pas de noyau délimité par une membrane nucléaire. Les cellules eucaryotes possèdent un noyau contenant le matériel génétique organisé en chromosomes. La mitose est la division cellulaire qui produit deux cellules filles génétiquement identiques et assure la croissance des organismes. La méiose produit des cellules reproductrices appelées gamètes avec un nombre de chromosomes réduit de moitié.",
    "La photosynthèse est le processus biochimique fondamental par lequel les végétaux chlorophylliens captent l'énergie lumineuse pour synthétiser des molécules organiques comme le glucose. Cette réaction se déroule dans les chloroplastes qui contiennent la chlorophylle le pigment vert qui absorbe la lumière solaire. La respiration cellulaire est le processus inverse qui libère de l'énergie en dégradant le glucose en présence d'oxygène pour produire de l'eau et du dioxyde de carbone.",
    "L'atome est la plus petite unité constitutive de la matière qui conserve les propriétés chimiques d'un élément. Le noyau atomique est composé de protons chargés positivement et de neutrons neutres et il concentre la quasi-totalité de la masse de l'atome. Les électrons chargés négativement gravitent autour du noyau dans des couches électroniques dont la configuration détermine les propriétés chimiques de l'élément. Les liaisons chimiques résultent des interactions entre les couches électroniques de valence de différents atomes.",
    "L'énergie est la grandeur physique qui caractérise la capacité d'un système à produire un travail ou à modifier son état. Le premier principe de la thermodynamique affirme que l'énergie d'un système isolé est constante et ne peut être ni créée ni détruite mais seulement transformée. La chaleur est un transfert d'énergie spontané d'un corps à température élevée vers un corps à température plus basse. L'énergie cinétique est proportionnelle à la masse et au carré de la vitesse d'un objet en mouvement.",
    "L'écosystème est un ensemble d'êtres vivants en interaction avec leur environnement physique et chimique. La chaîne alimentaire représente les relations de prédation entre producteurs consommateurs primaires consommateurs secondaires et décomposeurs. La biodiversité mesure la variété des espèces vivantes dans un écosystème et elle est essentielle à l'équilibre et à la résilience des milieux naturels. La conservation de l'environnement est un enjeu majeur pour le maintien des services écosystémiques dont dépendent les sociétés humaines.",
  ],
  'intro' => [
    "Voici l'explication scientifique :",
    "La science nous enseigne sur ce sujet :",
    "Analysons ce phénomène scientifique :",
  ]
],

'histoire' => [
  'keywords' => ['haïti','révolution','indépendance','histoire','1804','dessalines',
                  'toussaint','christophe','colonial','esclavage','liberté','africain',
                  'vertières','bois caïman','saint-domingue','pétion','boyer','boukman',
                  'capois','louisverture','duvalier','aristide','preval','martelly',
                  'empire','roi','empereur','guerres','batailles','traité','occupation'],
  'corpus' => [
    "La Révolution haïtienne est l'un des événements les plus importants de l'histoire moderne de l'humanité. Elle a débuté en août 1791 lors de la cérémonie du Bois Caïman organisée par Dutty Boukman qui réunit des esclaves pour préparer le soulèvement général contre le système esclavagiste colonial. Toussaint Louverture ancien esclave affranchi devint le principal chef militaire et politique de la révolution et transforma une armée d'insurgés en une force militaire disciplinée et respectée des grandes puissances européennes.",
    "La bataille de Vertières s'est déroulée le dix-huit novembre 1803 près du Cap-Haïtien et fut la dernière grande bataille de la Révolution haïtienne. Les forces haïtiennes commandées par Jean-Jacques Dessalines et François Capois dit Capois-la-Mort infligèrent une défaite décisive à l'armée française du général Rochambeau. Le premier janvier 1804 Jean-Jacques Dessalines proclama solennellement l'indépendance d'Haïti à Gonaïves faisant d'Haïti le premier État noir libre et souverain du monde.",
    "Saint-Domingue était considérée comme la colonie la plus productive du monde au dix-huitième siècle et représentait une source de richesse considérable pour la France. Elle fournissait environ quarante pourcent du sucre et plus de la moitié du café consommés en Europe grâce au travail forcé de plus de cinq cent mille esclaves africains. La brutalité du système esclavagiste et les contradictions entre les idéaux de liberté proclamés par la Révolution française et la réalité de l'esclavage furent les principales causes de la révolution haïtienne.",
    "Henri Christophe fut l'un des grands héros de la Révolution haïtienne et devint roi d'Haïti dans la partie nord du pays sous le nom de Henri Premier. Il est célèbre pour avoir fait construire la Citadelle Laferrière une forteresse monumentale dans les montagnes du nord d'Haïti classée patrimoine mondial de l'UNESCO. Alexandre Pétion dirigeait la République dans la partie sud et fut connu pour sa politique de redistribution des terres et pour avoir aidé Simón Bolívar dans sa lutte pour l'indépendance des nations sud-américaines.",
    "L'occupation américaine d'Haïti dura de 1915 à 1934 et eut des conséquences durables sur les institutions politiques et économiques du pays. Les marines américains modernisèrent certaines infrastructures mais imposèrent une constitution favorable aux intérêts étrangers et réintroduisèrent le travail forcé appelé corvée. Le mouvement indigéniste haïtien émergea en réaction à cette occupation comme une affirmation de l'identité culturelle africaine et haïtienne. Des intellectuels comme Jean Price-Mars promurent la valorisation des traditions vodou du créole et de la culture populaire haïtienne.",
    "La période duvaliériste qui s'étendit de 1957 à 1986 fut marquée par une dictature brutale qui utilisa les Tontons Macoutes une milice paramilitaire pour contrôler la population par la terreur. François Duvalier puis Jean-Claude Duvalier affaiblirent les institutions démocratiques appauvrirent le pays et provoquèrent l'exode de nombreux intellectuels et professionnels. La chute de Jean-Claude Duvalier en 1986 ouvrit une longue période de transition démocratique difficile marquée par des coups d'État successifs et une instabilité chronique.",
  ],
  'intro' => [
    "D'un point de vue historique :",
    "L'histoire d'Haïti nous enseigne que :",
    "Pour comprendre ce contexte historique :",
  ]
],

'geographie' => [
  'keywords' => ['géographie','département','capitale','port-au-prince','ville','région',
                  'artibonite','nord','sud','ouest','centre','nippes','gonaïves',
                  'cap-haïtien','jacmel','jérémie','hinche','hispaniola','caraïbes',
                  'montagne','fleuve','rivière','lac','plaine','côte','île','mer',
                  'climat','temperature','pluie','tropical','ouragan','cyclone'],
  'corpus' => [
    "Haïti est un pays des Caraïbes qui occupe le tiers occidental de l'île d'Hispaniola qu'il partage avec la République Dominicaine. Le territoire haïtien couvre une superficie d'environ vingt-sept mille sept cent cinquante kilomètres carrés. La population haïtienne est estimée à environ onze millions d'habitants faisant d'Haïti le pays le plus densément peuplé des Caraïbes. Port-au-Prince est la capitale nationale et le principal centre économique culturel et politique du pays.",
    "Haïti est administrativement divisé en dix départements chacun administré par un chef-lieu. Le département de l'Ouest dont le chef-lieu est Port-au-Prince est le plus peuplé et le plus urbanisé. Le département du Nord a pour chef-lieu Cap-Haïtien qui est la deuxième ville du pays. L'Artibonite dont le chef-lieu est Gonaïves est le département le plus vaste et constitue le principal grenier agricole du pays avec sa grande plaine irriguée.",
    "Le relief haïtien est essentiellement montagneux avec plusieurs chaînes de montagnes qui traversent le territoire. Le massif de la Selle culmine au pic La Selle à deux mille six cent quatre-vingts mètres qui est le point culminant d'Haïti. La plaine de l'Artibonite est la plus grande plaine agricole du pays et le fleuve Artibonite est le plus long cours d'eau haïtien. Le lac Azuéi est le plus grand lac du pays situé près de la frontière dominicaine dans le département de l'Ouest.",
    "Le climat d'Haïti est de type tropical maritime avec deux saisons des pluies et deux saisons sèches. La saison des ouragans s'étend de juin à novembre et représente une menace majeure pour le pays en raison de sa position géographique dans la zone de formation des cyclones atlantiques. Le tremblement de terre du douze janvier 2010 fut l'une des catastrophes naturelles les plus dévastatrices de l'histoire haïtienne causant la mort de plus de deux cent mille personnes. La déforestation massive est l'un des grands défis environnementaux d'Haïti car le couvert forestier ne dépasse plus quelques pourcents du territoire.",
  ],
  'intro' => [
    "Sur le plan géographique :",
    "En ce qui concerne la géographie haïtienne :",
    "Géographiquement parlant :",
  ]
],

'sciences_sociales' => [
  'keywords' => ['société','social','communauté','famille','religion','vodou','catholicisme',
                  'protestant','culture','tradition','langue','créole','français',
                  'discrimination','inégalité','pauvreté','classe sociale','migration',
                  'diaspora','genre','femme','droit','justice','solidarité','identité'],
  'corpus' => [
    "La société haïtienne est caractérisée par une grande diversité culturelle issue du mélange des traditions africaines européennes et autochtones taïnos. Le vodou haïtien est une religion syncrétique qui combine des croyances africaines principalement fon et yoruba avec des éléments catholiques et il constitue un pilier central de l'identité et de la culture haïtienne. Le créole haïtien est la langue maternelle de l'ensemble de la population tandis que le français reste la langue de l'administration et de l'enseignement formel.",
    "Les structures familiales haïtiennes sont souvent étendues avec plusieurs générations vivant ensemble et s'entraidant mutuellement. La famille joue un rôle fondamental dans les systèmes de soutien social et économique notamment à travers les pratiques de solidarité comme l'envoi de remises par la diaspora. La diaspora haïtienne représente plusieurs millions de personnes installées principalement aux États-Unis en République Dominicaine au Canada et en France et elle contribue significativement à l'économie nationale.",
    "Les sciences sociales étudient les comportements humains et les structures des sociétés en utilisant des méthodes rigoureuses d'observation et d'analyse. La sociologie analyse les structures sociales les institutions et les processus de changement. L'anthropologie étudie les cultures humaines dans leur diversité et leurs évolutions historiques. La psychologie sociale s'intéresse aux comportements individuels influencés par le contexte social et les interactions avec les autres membres d'un groupe.",
    "Les inégalités sociales en Haïti sont parmi les plus prononcées des Amériques avec un coefficient de Gini très élevé reflétant une concentration extrême des richesses. La pauvreté multidimensionnelle touche une grande partie de la population et se manifeste par des déficits en matière d'accès à l'éducation aux soins de santé à l'eau potable et à l'assainissement. Les femmes haïtiennes font face à des obstacles supplémentaires liés aux inégalités de genre notamment en termes d'accès à l'éducation et aux opportunités économiques.",
  ],
  'intro' => [
    "D'un point de vue des sciences sociales :",
    "Analysons la dimension sociale de cette question :",
    "La société haïtienne nous permet d'observer que :",
  ]
],

'politique' => [
  'keywords' => ['politique','gouvernement','président','parlement','sénat','député',
                  'constitution','loi','état','démocratie','élection','vote','pouvoir',
                  'justice','corruption','institution','parti','opposition','gel',
                  'souveraineté','diplomatie','relations internationales','onu','usa',
                  'droits humains','liberté expression','censure','crise','instabilité'],
  'corpus' => [
    "Le système politique haïtien est une République présidentielle où le pouvoir exécutif est exercé par le Président et le Premier Ministre assistés d'un Conseil des ministres. Le pouvoir législatif est confié au Parlement bicaméral composé du Sénat et de la Chambre des Députés. Le pouvoir judiciaire est exercé par les tribunaux dont la plus haute juridiction est la Cour de Cassation. La Constitution de 1987 qui définit ce cadre institutionnel fut adoptée après la chute de la dictature duvaliériste.",
    "La démocratie est un système de gouvernement dans lequel le pouvoir émane du peuple et s'exerce par ses représentants élus au suffrage universel. Les élections libres et transparentes sont le fondement d'un État démocratique et permettent aux citoyens de choisir leurs dirigeants et de les tenir responsables de leurs actions. La séparation des pouvoirs garantit que l'exécutif le législatif et le judiciaire exercent leurs fonctions de manière indépendante pour prévenir les abus de pouvoir.",
    "La corruption est l'un des principaux obstacles au développement politique et économique d'Haïti et ronge la confiance des citoyens dans leurs institutions. Elle se manifeste par le détournement de fonds publics le favoritisme dans l'attribution des marchés et l'impunité des élites politiques et économiques. La lutte contre la corruption nécessite des institutions judiciaires indépendantes une société civile active des médias libres et une volonté politique forte de l'État.",
    "Les droits humains sont l'ensemble des droits fondamentaux reconnus à tout être humain sans discrimination. Ils comprennent les droits civils et politiques comme la liberté d'expression la liberté de réunion et le droit à un procès équitable ainsi que les droits économiques sociaux et culturels comme le droit à l'éducation à la santé et au travail. La défense des droits humains est un enjeu majeur en Haïti où les violations sont fréquemment documentées par des organisations nationales et internationales.",
    "Les relations internationales d'Haïti sont marquées par une forte dépendance envers l'aide étrangère et une présence significative d'organisations internationales. La communauté internationale notamment les Nations Unies les États-Unis et l'Union européenne joue un rôle important dans le financement du budget de l'État et dans les opérations humanitaires. La souveraineté haïtienne est parfois perçue comme fragilisée par ces dépendances et par les conditions attachées à l'aide internationale.",
  ],
  'intro' => [
    "Sur le plan politique :",
    "En matière de gouvernance et politique :",
    "Pour analyser cette question politique :",
  ]
],

'economie' => [
  'keywords' => ['économie','pib','croissance','inflation','gourde','dollar','monnaie',
                  'agriculture','industrie','commerce','importation','exportation','commerce',
                  'investissement','banque','finance','budget','dette','pauvreté','chomage',
                  'salaire','revenu','marché','production','consommation','emploi','travail',
                  'remise','transfert','diaspora','cafe','cacao','mangue','sucre','coton'],
  'corpus' => [
    "L'économie haïtienne est l'une des plus fragiles de l'hémisphère occidental et elle repose principalement sur l'agriculture le secteur informel et les remises de la diaspora. Le Produit Intérieur Brut par habitant est parmi les plus bas des Amériques ce qui reflète un niveau de développement économique très limité. L'agriculture emploie encore une grande partie de la population active malgré sa faible productivité due au manque d'irrigation à la déforestation et à l'accès limité aux intrants modernes.",
    "Les remises de la diaspora haïtienne représentent une part considérable du Produit Intérieur Brut haïtien et constituent une source de revenus vitale pour de nombreuses familles. Ces transferts de fonds permettent à des millions de ménages d'accéder à des biens et services essentiels comme la nourriture les soins de santé et l'éducation. Cependant cette dépendance aux remises représente aussi une vulnérabilité car elle expose l'économie haïtienne aux fluctuations économiques des pays d'accueil de la diaspora.",
    "L'inflation est l'augmentation générale et durable du niveau des prix qui érode le pouvoir d'achat des ménages. En Haïti l'inflation est alimentée par la dépréciation continue de la gourde la forte dépendance aux importations alimentaires et énergétiques et les perturbations de l'offre causées par l'insécurité. La banque centrale haïtienne la Banque de la République d'Haïti utilise les instruments de politique monétaire pour tenter de stabiliser les prix et le taux de change.",
    "L'agriculture haïtienne produit principalement des cultures vivrières comme le maïs le sorgho le manioc et les légumes ainsi que des cultures d'exportation comme le café le cacao la mangue et le vétiver. Le secteur agricole souffre de nombreuses contraintes structurelles notamment le morcellement des terres la dégradation des sols le manque d'infrastructure d'irrigation et les difficultés d'accès au crédit agricole. Le développement agricole est pourtant essentiel pour améliorer la sécurité alimentaire du pays et créer des emplois en zones rurales.",
    "Le secteur industriel haïtien est dominé par l'industrie textile et de l'assemblage qui représente la principale source de devises d'exportation. Les zones franches industrielles notamment dans la région de Port-au-Prince et de Caracol emploient des dizaines de milliers de travailleurs essentiellement dans la confection de vêtements destinés au marché américain. Le tourisme représente un potentiel économique important encore largement sous-exploité malgré les richesses naturelles et culturelles du pays.",
  ],
  'intro' => [
    "Sur le plan économique :",
    "En matière d'économie haïtienne :",
    "Pour comprendre cette réalité économique :",
  ]
],

'education' => [
  'keywords' => ['apprendre','étudier','école','université','cours','réviser','examen',
                  'méthode','productivité','concentration','mémoire','note','devoir',
                  'enseignant','élève','étudiant','baccalauréat','diplôme','formation',
                  'comprendre','retenir','mémoriser','préparer','organiser','technique',
                  'bachotage','révision','quiz','fiche','résumé','schéma','mnémotechnique'],
  'corpus' => [
    "L'apprentissage efficace repose sur plusieurs principes fondamentaux issus des neurosciences cognitives. La répétition espacée est l'une des techniques les plus puissantes pour ancrer durablement des informations en mémoire à long terme. Elle consiste à réviser les informations à des intervalles croissants ce qui optimise considérablement la consolidation mémorielle. La pratique de récupération active qui consiste à se tester régulièrement plutôt que de relire ses notes est également très efficace.",
    "La méthode Pomodoro est une technique de gestion du temps très efficace pour les étudiants. Elle consiste à travailler de façon intensive pendant vingt-cinq minutes puis à faire une pause de cinq minutes. Après quatre cycles de travail on s'accorde une pause plus longue de quinze à trente minutes. Cette alternance entre effort et repos permet de maintenir un niveau de concentration élevé tout en évitant la fatigue mentale et la procrastination.",
    "La prise de notes efficace est une compétence essentielle pour tout étudiant. La méthode Cornell propose de diviser la page en trois zones une colonne étroite à gauche pour les mots-clés une grande zone à droite pour les notes détaillées et un espace en bas pour le résumé. Il est conseillé de reformuler les informations avec ses propres mots plutôt que de copier mot à mot ce qui favorise la compréhension et la mémorisation durable.",
    "La gestion du stress avant les examens est essentielle pour performer au meilleur de ses capacités. Une préparation régulière et progressive est beaucoup plus efficace que le bachotage intensif de la veille. Dormir suffisamment est indispensable car le sommeil consolide les apprentissages en mémoire à long terme. L'exercice physique régulier améliore les fonctions cognitives et réduit l'anxiété ce qui favorise une meilleure concentration lors des révisions.",
    "Les cartes mentales ou mind maps sont des outils visuels puissants pour organiser et mémoriser des informations complexes. Elles permettent de représenter graphiquement les relations entre les concepts et facilitent la mémorisation grâce à l'utilisation de couleurs de symboles et de mots-clés. Les schémas et les illustrations favorisent la compréhension des phénomènes abstraits en les rendant concrets et visuellement accessibles. La technique des acronymes et des phrases mnémotechniques est utile pour mémoriser des listes ou des séquences ordonnées.",
  ],
  'intro' => [
    "Pour améliorer votre apprentissage :",
    "En matière de méthodes d'étude :",
    "Pour réussir dans vos études :",
  ]
],

'agri_environnement' => [
  'keywords' => ['agriculture','agri','environnement','forêt','déforestation','sol','terre',
                  'culture','récolte','plante','arbre','eau','irrigation','fertilisant',
                  'pesticide','bio','organique','climate','changement climatique','erosion',
                  'montagne','rivière','conservation','reboisement','jardin','semence'],
  'corpus' => [
    "L'agriculture haïtienne est confrontée à de graves défis environnementaux dont le plus critique est la déforestation qui a réduit le couvert forestier du pays à moins de deux pourcent de sa superficie totale. Cette dégradation provoque une érosion intense des sols une réduction des ressources en eau et une augmentation de la vulnérabilité aux catastrophes naturelles comme les inondations et les glissements de terrain. Le reboisement et la conservation des bassins versants sont des priorités absolues pour restaurer les écosystèmes et garantir la durabilité de l'agriculture.",
    "L'agroécologie est une approche agricole qui combine les savoirs traditionnels et les connaissances scientifiques pour développer des systèmes de production durables et respectueux de l'environnement. Elle privilégie la diversification des cultures l'utilisation de composte et d'engrais organiques la gestion intégrée des nuisibles et la conservation de la biodiversité agricole. Ces pratiques améliorent la résilience des systèmes agricoles face aux chocs climatiques et économiques tout en préservant la fertilité des sols sur le long terme.",
    "Le changement climatique représente une menace croissante pour l'agriculture haïtienne et pour les populations rurales vulnérables. L'augmentation des températures les modifications des régimes pluviométriques et l'intensification des phénomènes climatiques extrêmes comme les ouragans et les sécheresses affectent directement les rendements agricoles et la sécurité alimentaire. L'adaptation au changement climatique nécessite des investissements dans des variétés végétales résistantes à la sécheresse des systèmes d'irrigation améliorés et des pratiques de gestion durable des terres.",
    "La gestion de l'eau est un défi majeur pour l'agriculture haïtienne car une grande partie du territoire souffre d'un déficit hydrique pendant la saison sèche. L'irrigation permet d'étendre les surfaces cultivables et d'augmenter les rendements mais les infrastructures hydrauliques restent insuffisantes et mal entretenues dans la plupart des zones rurales. La collecte des eaux de pluie et la construction de petits réservoirs constituent des solutions accessibles pour les agriculteurs familiaux confrontés au manque d'eau.",
  ],
  'intro' => [
    "Sur le plan agricole et environnemental :",
    "En matière d'agriculture et d'écologie :",
    "Pour comprendre ces enjeux environnementaux :",
  ]
],

'kreyol_lang' => [
  'keywords' => ['créole','kreyòl','langue','français','linguistique','parler','mot',
                  'grammaire','littérature','poésie','chanson','proverbe','diton',
                  'traduction','bilinguisme','haïtien','expression','oral','écrit'],
  'corpus' => [
    "Le créole haïtien est la langue maternelle de l'ensemble de la population haïtienne et il est l'une des deux langues officielles du pays avec le français. Il est né de la rencontre entre les langues africaines principalement fon et yoruba le français colonial et les influences taïnos et espagnoles. Malgré son statut officiel le créole a longtemps été marginalisé au profit du français dans les institutions l'enseignement et les médias formels.",
    "La grammaire créole haïtienne diffère significativement du français notamment par l'absence de déclinaisons la structure syntaxique sujet verbe objet et l'utilisation de marqueurs aspectuels plutôt que de conjugaisons verbales complexes. Le créole haïtien est une langue à part entière avec ses propres règles phonologiques morphologiques et syntaxiques et non pas un simple dérivé du français. Des linguistes comme Albert Valdman et Yves Dejan ont contribué à la codification et à la standardisation de l'orthographe créole.",
    "Les proverbes haïtiens appelés pwovèb en créole constituent un trésor de la sagesse populaire et reflètent les valeurs la vision du monde et l'expérience collective du peuple haïtien. Des expressions comme Dèyè mòn gen mòn qui signifie littéralement derrière les montagnes il y a des montagnes illustrent la résilience et le courage face aux obstacles. La littérature orale haïtienne comprend des contes des légendes des devinettes et des chansons qui sont transmis de génération en génération.",
    "La littérature haïtienne écrite en français et en créole est riche et diverse avec des auteurs de renommée internationale comme Jacques Roumain Frankétienne Edwidge Danticat et René Dépestre. Le mouvement indigéniste des années trente et quarante a produit une littérature engagée qui valorisait la culture africaine et créole contre l'influence coloniale et l'occupation américaine. Frankétienne est considéré comme le père de la littérature en langue créole haïtienne et ses spiralistes romans comme Dezafi constituent des œuvres majeures.",
  ],
  'intro' => [
    "Sur le plan linguistique et culturel :",
    "En ce qui concerne la langue et la culture haïtienne :",
    "Pour comprendre cette richesse culturelle :",
  ]
],

]; // fin $DOMAINES

// ────────────────────────────────────────────────────────────
//  4. SYNONYMES ET EXPANSION SÉMANTIQUE
// ────────────────────────────────────────────────────────────
$SYNONYMES = [
    'maths' => 'mathématiques', 'math' => 'mathématiques',
    'physique' => 'physique', 'bio' => 'biologie',
    'histoire' => 'histoire', 'haiti' => 'haïti', 'haïtien' => 'haïti',
    'eco' => 'économie', 'economie' => 'économie',
    'politique' => 'politique', 'gouvernement' => 'politique',
    'geo' => 'géographie', 'geographie' => 'géographie',
    'anglais' => 'langue', 'kreyol' => 'créole', 'creole' => 'créole',
    'agri' => 'agriculture', 'farming' => 'agriculture',
    'env' => 'environnement', 'nature' => 'environnement',
    'société' => 'sciences_sociales', 'culture' => 'sciences_sociales',
];

function expandMessage(string $msg, array $synonymes): string {
    foreach ($synonymes as $abr => $complet) {
        $msg = preg_replace('/\b' . preg_quote($abr, '/') . '\b/iu', $complet, $msg);
    }
    return $msg;
}

// ────────────────────────────────────────────────────────────
//  5. DÉTECTION DE RÉPONSE DIRECTE (faits précis)
// ────────────────────────────────────────────────────────────
function chercherFait(string $msg, array $faits): ?string {
    $msgLow = mb_strtolower($msg, 'UTF-8');
    foreach ($faits as $fait) {
        foreach ($fait['trigger'] as $trigger) {
            if (mb_strpos($msgLow, mb_strtolower($trigger, 'UTF-8')) !== false) {
                return $fait['reponse'];
            }
        }
    }
    return null;
}

// ────────────────────────────────────────────────────────────
//  6. DÉTECTION D'INTENTION AMÉLIORÉE
// ────────────────────────────────────────────────────────────
function detecterIntention(string $msg, array $domaines): array {
    $scores = [];
    foreach ($domaines as $nom => $data) {
        $score = 0;
        foreach ($data['keywords'] as $kw) {
            if (mb_strpos($msg, mb_strtolower($kw, 'UTF-8')) !== false) $score += 2;
        }
        $scores[$nom] = $score;
    }
    arsort($scores);
    $meilleurDom = array_key_first($scores);
    $meilleurScore = $scores[$meilleurDom];

    $type = 'explication';
    if (preg_match('/\b(qu\'est[- ]ce|c\'est quoi|définition|définir|signifie|expliquer|kesako|kisa)\b/iu', $msg))
        $type = 'definition';
    elseif (preg_match('/\b(comment|étapes|procédure|méthode|kijan)\b/iu', $msg))
        $type = 'methode';
    elseif (preg_match('/\b(pourquoi|raison|cause|poutèt|poukisa)\b/iu', $msg))
        $type = 'explication';
    elseif (preg_match('/\b(exemple|exercice|application|egzanp)\b/iu', $msg))
        $type = 'exemple';
    elseif (preg_match('/\b(résumé|synthèse|points clés|retenir|rezime)\b/iu', $msg))
        $type = 'resume';

    return ['domaine' => $meilleurDom, 'score' => $meilleurScore, 'type' => $type, 'tous_scores' => $scores];
}

// ────────────────────────────────────────────────────────────
//  7. CHAÎNE DE MARKOV ORDRE 3
// ────────────────────────────────────────────────────────────
function construireChaine(array $corpus, int $ordre = 3): array {
    $chaine = [];
    $departs = [];
    foreach ($corpus as $texte) {
        $texte = preg_replace('/\.\s+/', '. <FIN> ', $texte);
        $mots = array_values(array_filter(preg_split('/\s+/', trim($texte))));
        $n = count($mots);
        if ($n > $ordre) {
            $departs[] = implode(' ', array_slice($mots, 0, $ordre));
        }
        for ($i = 0; $i < $n - $ordre; $i++) {
            $cle = implode(' ', array_slice($mots, $i, $ordre));
            $chaine[$cle][] = $mots[$i + $ordre];
        }
    }
    return ['t' => $chaine, 'd' => array_unique($departs)];
}

// ────────────────────────────────────────────────────────────
//  8. GÉNÉRATION AVEC BACKOFF + PONDÉRATION
// ────────────────────────────────────────────────────────────
function generer(array $modele, int $nbPhrases = 3, int $ordre = 3): string {
    $t = $modele['t'];
    $d = $modele['d'];
    $phrases = [];
    if (empty($t) || empty($d)) return '';

    for ($p = 0; $p < $nbPhrases; $p++) {
        $debut = $d[array_rand($d)];
        $mots  = explode(' ', $debut);

        for ($i = 0; $i < 50; $i++) {
            $suivant = null;
            for ($o = $ordre; $o >= 1; $o--) {
                $cle = implode(' ', array_slice($mots, -$o));
                if (!empty($t[$cle])) {
                    $candidats = $t[$cle];
                    $freq = array_count_values($candidats);
                    arsort($freq);
                    // 60% top fréquent, 40% aléatoire → plus de variété
                    $suivant = (rand(1,100) <= 60)
                        ? array_key_first($freq)
                        : $candidats[array_rand($candidats)];
                    break;
                }
            }
            if ($suivant === null || $suivant === '<FIN>') break;
            $mots[] = $suivant;
        }

        $phrase = trim(str_replace('<FIN>', '', implode(' ', $mots)));
        $phrase = preg_replace('/\s+/', ' ', $phrase);
        if (mb_strlen($phrase, 'UTF-8') > 30) $phrases[] = ucfirst($phrase);
    }

    return implode('. ', $phrases);
}

// ────────────────────────────────────────────────────────────
//  9. POST-TRAITEMENT
// ────────────────────────────────────────────────────────────
function postTraiter(string $t): string {
    $t = preg_replace('/\.{2,}/', '.', $t);
    $t = preg_replace('/\.\s*\./', '.', $t);
    $t = preg_replace_callback('/\.\s+([a-zàâäéèêëîïôöùûüÿ])/u',
        fn($m) => '. ' . mb_strtoupper($m[1], 'UTF-8'), $t);
    $t = preg_replace('/\b(\w+)(\s+\1)+\b/iu', '$1', $t);
    $t = rtrim($t, ' .,;:') . '.';
    return trim($t);
}

// ────────────────────────────────────────────────────────────
//  10. ASSEMBLAGE FINAL AVEC TEMPLATE RICHE
// ────────────────────────────────────────────────────────────
function assembler(string $texte, array $intention, array $domaines): string {
    $dom  = $intention['domaine'];
    $type = $intention['type'];

    $intro = "Voici une réponse à votre question :";
    if ($dom && isset($domaines[$dom]['intro'])) {
        $arr   = $domaines[$dom]['intro'];
        $intro = $arr[array_rand($arr)];
    }

    $connecteurs = [
        'definition'  => ["En d'autres termes,","Plus précisément,","Pour être exact,"],
        'methode'     => ["Pour y parvenir,","Concrètement,","En pratique,"],
        'explication' => ["En effet,","Cela s'explique par le fait que","Ce phénomène résulte du fait que"],
        'exemple'     => ["Par exemple,","À titre d'illustration,","Prenons le cas où"],
        'resume'      => ["En résumé,","Pour synthétiser,","Les points essentiels sont :"],
    ];
    $connecteur = '';
    if (isset($connecteurs[$type])) {
        $arr = $connecteurs[$type];
        $connecteur = $arr[array_rand($arr)];
    }

    $suggestions = [
        "Souhaitez-vous un exemple concret ou un exercice pratique ?",
        "Voulez-vous que je génère une fiche de révision complète sur ce thème ?",
        "Avez-vous des questions plus spécifiques sur un aspect particulier ?",
        "Je peux aussi vous proposer un quiz pour tester votre compréhension.",
        "Besoin d'une explication plus approfondie sur un point précis ?",
    ];

    $r  = "**$intro**\n\n";
    $r .= $texte . "\n\n";
    if ($connecteur) {
        $r .= "_$connecteur_ ce concept est fondamental pour progresser dans ce domaine d'étude.\n\n";
    }
    $r .= "💡 " . $suggestions[array_rand($suggestions)];
    return $r;
}

// ────────────────────────────────────────────────────────────
//  11. SAUVEGARDER EN BDD
// ────────────────────────────────────────────────────────────
function sauvegarder(?PDO $pdo, string $q, string $r, ?string $dom): void {
    if (!$pdo) return;
    try {
        $pdo->prepare(
            "INSERT IGNORE INTO conversations (question, reponse, domaine, cree_le) VALUES (?,?,?,NOW())"
        )->execute([$q, $r, $dom]);
    } catch (PDOException $e) { /* silencieux */ }
}

// ────────────────────────────────────────────────────────────
//  12. PIPELINE PRINCIPAL
// ────────────────────────────────────────────────────────────

// Étape 1 : expansion sémantique
$msgExpanded = expandMessage($msgLow, $SYNONYMES);

// Étape 2 : vérifier si réponse directe disponible
$reponseDirect = chercherFait($msgExpanded, $FAITS);
if ($reponseDirect) {
    $pdo = getDB($DB);
    sauvegarder($pdo, $message, $reponseDirect, 'faits');
    echo json_encode([
        'response' => $reponseDirect,
        'meta'     => ['domaine'=>'faits','type'=>'direct','score'=>10,'ordre_markov'=>0]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Étape 3 : détection d'intention
$intention = detecterIntention($msgExpanded, $DOMAINES);

// Étape 4 : sélection du corpus
if ($intention['domaine'] && $intention['score'] >= 2) {
    $corpusChoisi = $DOMAINES[$intention['domaine']]['corpus'];
    // Si plusieurs domaines proches, fusionner pour plus de richesse
    foreach ($intention['tous_scores'] as $dom => $score) {
        if ($dom !== $intention['domaine'] && $score >= 2) {
            $corpusChoisi = array_merge($corpusChoisi, array_slice($DOMAINES[$dom]['corpus'], 0, 2));
        }
    }
} else {
    $corpusChoisi = [];
    foreach ($DOMAINES as $d) {
        $corpusChoisi = array_merge($corpusChoisi, array_slice($d['corpus'], 0, 2));
    }
    $intention['domaine'] = 'general';
}

// Étape 5 : génération
$modele      = construireChaine($corpusChoisi, 3);
$texteRaw    = generer($modele, 3, 3);
$textePropre = postTraiter($texteRaw);
$reponse     = assembler($textePropre, $intention, $DOMAINES);

// Étape 6 : sauvegarde
$pdo = getDB($DB);
sauvegarder($pdo, $message, $reponse, $intention['domaine']);

echo json_encode([
    'response' => $reponse,
    'meta'     => [
        'domaine'      => $intention['domaine'],
        'score'        => $intention['score'],
        'type'         => $intention['type'],
        'ordre_markov' => 3,
    ]
], JSON_UNESCAPED_UNICODE);
?>
