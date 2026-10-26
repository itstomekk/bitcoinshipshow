<?php
/**
 * The design registry. One entry per skin.
 *
 * To add a design: drop a CSS file in /css/, add a row here. Nothing else.
 *
 *   slug   => folder-safe id, becomes ?design=<slug> and body class design-<slug>
 *   name   => label in the picker
 *   note   => one-line description, shown as the picker button's tooltip
 *   fonts  => full <link href> for Google Fonts, or null if the design uses system fonts
 *   dark   => true if the skin is dark, used only to colour the picker itself
 */
return [

    'current' => [
        'name'  => 'Neon Brutalist',
        'note'  => 'The original: dark paper, lime/pink/cyan, rotated cards',
        'fonts' => 'https://fonts.googleapis.com/css2?family=Archivo+Black&family=Space+Grotesk:wght@500;700&family=Space+Mono:wght@400;700&display=swap',
        'dark'  => true,
    ],

    'popart' => [
        'name'  => 'Brutalist Pop Art',
        'note'  => 'Dark navy sea, cream paper, deep red, halftones',
        'fonts' => 'https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@300;500;700&display=swap',
        'dark'  => true,
    ],

    'ocean' => [
        'name'  => 'Open Water',
        'note'  => 'Bright cerulean ripples, hot pink and orange inks',
        'fonts' => 'https://fonts.googleapis.com/css2?family=Anton&family=Oswald:wght@300;500;700&display=swap',
        'dark'  => false,
    ],

    'pirate' => [
        'name'  => 'Letters of Marque',
        'note'  => 'Aged parchment, black water, gold leaf and blood red',
        'fonts' => 'https://fonts.googleapis.com/css2?family=Pirata+One&family=IM+Fell+English:ital@0;1&display=swap',
        'dark'  => true,
    ],

    // A fork of `pirate`, not a variant. Fixes do not propagate between the two.
    'corsair' => [
        'name'  => 'Blue Ensign',
        'note'  => 'Pirate, gone cyanotype: blueprint paper, deep marine, gold',
        'fonts' => 'https://fonts.googleapis.com/css2?family=Pirata+One&family=IM+Fell+English:ital@0;1&display=swap',
        'dark'  => true,
    ],

];
