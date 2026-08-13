<?php

/*
 * Diese Seite sieht jemand, der gerade etwas abbestellen wollte. Kurz, sachlich,
 * ohne Werbung und ohne Rückgewinnungsversuche.
 *
 * Geduzt, aus demselben Grund wie `de/mail.php`: die Mail, die hierher führt,
 * duzt. Ein Wechsel der Anrede zwischen Link und Zielseite ist genau die Art
 * Bruch, die eine Seite unecht wirken lässt.
 */

return [

    'title' => 'E-Mail-Einstellungen',
    'intro' => 'Diese Einstellungen gelten für :email.',

    'lists_heading' => 'Verteiler',
    'lists_hint' => 'Redaktionelle E-Mails. Sie beruhen auf deiner Einwilligung und können jederzeit beendet werden.',
    'lists_empty' => 'Für diese Adresse ist derzeit kein Verteiler eingerichtet.',
    'lists_blocked' => 'Der Versand an diese Adresse ist gesperrt. Eine Anmeldung ist hier nicht möglich.',

    'types_heading' => 'Produkt-Benachrichtigungen',
    'types_hint' => 'Hinweise aus dem Produkt, je Art und Weg.',
    'types_empty' => 'Diese Installation kennt keine Benachrichtigungsarten.',
    'types_column' => 'Art',
    'types_required' => 'Notwendig: Kontosicherheit und rechtliche Hinweise lassen sich nicht abschalten.',

    'channel_in_app' => 'Im Produkt',
    'channel_mail' => 'E-Mail',
    'channel_digest' => 'Sammelmail',

    'frequency_heading' => 'Häufigkeit',
    'frequency_hint' => 'Gilt für alle abschaltbaren Benachrichtigungen unten. Eine Änderung hier setzt deren E-Mail- und Sammelmail-Einstellung neu.',
    'frequency_mixed' => 'Derzeit gemischt: einige Benachrichtigungen kommen sofort, andere gesammelt. Wähle eine Option, um das zu vereinheitlichen.',
    'frequency_immediate' => 'Sofort',
    'frequency_immediate_desc' => 'Jede Benachrichtigung kommt einzeln, sobald sie entsteht.',
    'frequency_daily' => 'Täglich',
    'frequency_daily_desc' => 'Einmal am Tag gesammelt.',
    'frequency_weekly' => 'Wöchentlich',
    'frequency_weekly_desc' => 'Einmal in der Woche gesammelt.',
    'frequency_never' => 'Nie',
    'frequency_never_desc' => 'Keine E-Mails zu Produkt-Benachrichtigungen. Im Produkt siehst du sie weiterhin.',

    'suppression_blocked' => 'An diese Adresse wird derzeit nicht zugestellt. Der Grund liegt beim Postfach selbst, etwa eine Rückläufer- oder Beschwerdemeldung. Diese Sperre lässt sich hier nicht aufheben; abmelden kannst du dich weiterhin.',
    'suppression_unavailable' => 'Der Sperrstatus dieser Adresse ist gerade nicht abfragbar. Solange das so ist, wird nichts freigeschaltet, was gesperrt sein könnte.',

    'save' => 'Einstellungen speichern',
    'saved' => '{0} Es wurde nichts geändert.|{1} Eine Änderung gespeichert.|[2,*] :count Änderungen gespeichert.',

    'all_heading' => 'Von allem abmelden',
    'all_body' => 'Beendet alle oben genannten Verteiler dieser Marke auf einmal. E-Mails, die nicht auf einer Einwilligung beruhen, etwa die Bestätigung zu einer Bestellung, bleiben davon unberührt.',
    'all_button' => 'Von allen Verteilern abmelden',
    'all_done' => '{0} Es war nichts aktiv.|{1} Ein Verteiler wurde beendet.|[2,*] :count Verteiler wurden beendet.',

    'refused_blocked' => 'Gesperrt. Diese Einstellung wurde nicht geändert.',
    'refused_required' => 'Notwendig. Diese Benachrichtigung lässt sich nicht abschalten.',
    'refused_unidentified' => 'Zu dieser Adresse gehört noch kein Konto und kein Kontakt. Benachrichtigungen lassen sich deshalb hier nicht speichern.',
    'refused_unknown' => 'Eine ausgewählte Einstellung existiert nicht und wurde übergangen.',
    'refused_source_absent' => 'Dieser Bereich ist auf dieser Installation nicht eingerichtet.',

    'nothing_installed' => 'Für diese Adresse ist hier nichts einzustellen.',

    'proof_unsubscribe_token' => 'Du bist über den Link aus einer E-Mail hier. Jede Änderung wird mit diesem Nachweis protokolliert.',
    'proof_magic_link' => 'Du bist über einen angeforderten Link hier. Jede Änderung wird mit diesem Nachweis protokolliert.',
    'proof_session' => 'Du bist angemeldet. Jede Änderung wird mit diesem Nachweis protokolliert.',

    'request_title' => 'Einstellungen aufrufen',
    'request_intro' => 'Gib deine Adresse ein. Wenn wir sie erreichen können, schicken wir einen Link zu deinen Einstellungen.',
    'request_label' => 'E-Mail-Adresse',
    'request_placeholder' => 'name@beispiel.de',
    'request_button' => 'Link schicken',
    'request_foot' => 'Der Link gilt :minutes Minuten und führt nur zu diesen Einstellungen.',
    'magic_link_sent' => 'Wenn wir diese Adresse erreichen können, ist ein Link unterwegs. Aus Datenschutzgründen sagen wir an dieser Stelle nicht, ob eine Adresse bei uns bekannt ist.',

];
