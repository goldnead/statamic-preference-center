<?php

return [

    // Beschriftungen der Einstellungs-Seite. Die Seite selbst gehört
    // statamic-brand-context; dieses Addon liefert nur die Feldliste
    // (Support\Settings) und die Wörter dazu.

    'permission_manage_settings' => 'Einstellungen des Präferenzzentrums verwalten',

    // Die zwei Antworten eines Quellen-Schalters. Kein „an": ob ein Block
    // erscheint, entscheidet immer auch, ob sein Paket installiert ist.
    'source_options' => [
        'auto' => 'Anzeigen, wenn das Paket da ist',
        'off' => 'Nicht anzeigen',
    ],

    'groups' => [

        'magic_link' => [
            'title' => 'Magic Link',
            'description' => 'Der dritte Weg auf die Seite, für jemanden ohne Konto und ohne alte Mail. Adresse, Middleware und der Hauptschalter der Routen bleiben in config/preference-center.php: sie werden gelesen, während die Routen entstehen, und eine Änderung käme dort erst nach dem nächsten Deploy an.',
        ],

        'throttle' => [
            'title' => 'Drosselung',
            'description' => 'Die Anforderung eines Magic Link ist öffentlich und unangemeldet, das ist ihr Zweck. Zwei Zähler halten sie davon ab, ein Werkzeug zu werden: einer schützt das einzelne Postfach, einer verhindert, dass jemand die Adressliste eines anderen über diesen Endpunkt anschreiben lässt.',
        ],

        'sender' => [
            'title' => 'Absender',
            'description' => 'Wer den Magic Link verschickt, wenn die Marke keine eigene Absenderidentität hat. Hat sie eine, gewinnt die.',
        ],

        'sources' => [
            'title' => 'Blöcke der Seite',
            'description' => 'Jeder Block kommt aus einem Paket, das installiert sein kann oder nicht. Aus heißt: auch dann nicht anzeigen, wenn es installiert ist. Einen Block einzuschalten, dessen Paket fehlt, geht nicht, weil es dann nichts zu fragen gäbe. Der Marketing-Block fehlt hier mit Absicht und bleibt in config/preference-center.php: er entscheidet mit, ob die Token-Route überhaupt entsteht, und das passiert lange vor dieser Seite.',
        ],

        'audit' => [
            'title' => 'Prüfspur',
            'description' => 'Jede angewandte Änderung wird als Ereignis gemeldet und mit dem Nachweis protokolliert, der sie erlaubt hat. Welcher Logkanal das aufnimmt, bleibt in config/preference-center.php, weil die Kanäle dem Host gehören.',
        ],

    ],

    'fields' => [

        'magic_link_enabled' => [
            'label' => 'Magic Link anbieten',
            'description' => 'Aus antworten die beiden Adressen /request mit 404, und die Seite ist nur noch über einen Marketing-Token oder eine Anmeldung erreichbar. Bereits verschickte Links hören damit auf zu funktionieren.',
        ],
        'magic_link_ttl_minutes' => [
            'label' => 'Gültigkeit des Links (Minuten)',
            'description' => 'Wie lange die signierte Adresse nach dem Versand geöffnet werden kann. Kurz mit Absicht: anders als der Abmeldelink aus einer Marketingmail zeigt nichts in einer zwei Jahre alten Mail hierher. Die Zahl steht auch im Text der Mail, die den Link verschickt.',
        ],
        'magic_link_session_minutes' => [
            'label' => 'Sitzung nach dem Öffnen (Minuten)',
            'description' => 'Wie lange jemand nach dem Klick auf den Link auf der Seite bleiben darf, bevor er einen neuen anfordern muss. Verkürzen beendet keine laufende Sitzung, sondern gilt für die nächste.',
        ],
        'magic_link_min_response_ms' => [
            'label' => 'Mindestlaufzeit der Antwort (ms)',
            'description' => 'Ein Boden unter der Antwortzeit einer Linkanforderung, damit der schnelle Fall (Adresse unbekannt, nichts zu tun) sich nicht vom langsamen (Adresse gefunden, Mail eingereiht) unterscheiden lässt. Ohne ihn verrät die Antwortzeit, wer bei Ihnen im Verteiler steht. Höher setzen, wenn Ihr Mailer auf dieser Maschine langsamer ist als der Wert.',
        ],
        'magic_link_allow_unknown_addresses' => [
            'label' => 'Links auch an unbekannte Adressen schicken',
            'description' => 'Aus, und das soll so bleiben. An verschickt dieser Endpunkt einen signierten Link an alles, was jemand hineintippt, und ist damit ein offener Mailversender mit Zwischenschritten. Es gibt genau einen ehrlichen Fall dafür: eine Installation ohne Verteiler und ohne Kontaktspeicher, in der noch niemand bekannt ist.',
        ],

        'magic_link_throttle_per_address_max' => [
            'label' => 'Anforderungen je Adresse',
            'description' => 'Wie viele Links dieselbe Adresse im Zeitfenster unten anfordern darf. Der Zähler hängt an der Adresse, nicht an der Marke: ein Postfach ist ein Postfach, egal wie viele Marken der Host betreibt.',
        ],
        'magic_link_throttle_per_address_decay_minutes' => [
            'label' => 'Zeitfenster je Adresse (Minuten)',
            'description' => 'Wie lange ein verbrauchter Versuch zählt. Änderungen wirken auf neue Versuche; laufende Sperren laufen mit ihrem alten Fenster ab.',
        ],
        'magic_link_throttle_per_origin_max' => [
            'label' => 'Anforderungen je Herkunft',
            'description' => 'Wie oft dieselbe Herkunft im Zeitfenster unten Links anfordern darf, gleich für welche Adressen. Das ist der Zähler, der verhindert, dass jemand hier eine fremde Adressliste durchschickt.',
        ],
        'magic_link_throttle_per_origin_decay_minutes' => [
            'label' => 'Zeitfenster je Herkunft (Minuten)',
            'description' => 'Wie lange ein verbrauchter Versuch je Herkunft zählt.',
        ],

        'magic_link_from_address' => [
            'label' => 'Absenderadresse',
            'description' => 'Von wem die Mail mit dem Magic Link kommt, solange die Marke keine eigene Absenderidentität hat. Leer nimmt die Absenderadresse der Anwendung. Eine Adresse, die Ihr Versanddienst nicht kennt, führt zu abgelehnten Mails, nicht zu einer Fehlermeldung hier.',
        ],
        'magic_link_from_name' => [
            'label' => 'Absendername',
            'description' => 'Der Name neben der Adresse. Leer schickt die Mail nur mit der Adresse.',
        ],

        'sources_notifications' => [
            'label' => 'Produktbenachrichtigungen anzeigen',
            'description' => 'Die Matrix der Benachrichtigungen und der Block für den Rhythmus, aus goldnead/statamic-notifications. Aus verschwindet der Block von der Seite, und die Schreibwege lehnen ihn genauso entschieden ab, wie die Anzeige ihn weglässt: es ist kein Anzeige-Trick, den ein hartnäckiger Absender umgeht.',
        ],
        'sources_suppression' => [
            'label' => 'Sperren anzeigen',
            'description' => 'Der Zustand aus goldnead/statamic-suppression: was blockiert ist und warum. Aus meldet die Seite nichts als blockiert, obwohl es blockiert sein kann. Das ist der eine Block, dessen Abschalten die Seite unehrlich macht.',
        ],
        'sources_sequences' => [
            'label' => 'Strecken anzeigen',
            'description' => 'Die laufenden Automationsstrecken, in denen die Person steckt. Aus sieht sie sie nicht und kann sie hier nicht verlassen.',
        ],

        'audit_leadhub' => [
            'label' => 'Änderungen auf die CRM-Zeitachse schreiben',
            'description' => 'Mit goldnead/statamic-leadhub landet jede angewandte Änderung zusätzlich auf der Zeitachse des Kontakts, wo eine Auskunftsanfrage nach ihr suchen wird. Aus bleibt nur der Logeintrag. Rückwirkend wird nichts nachgetragen.',
        ],

    ],

];
