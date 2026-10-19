package org.alexdev.kepler.game.moderation;

import org.alexdev.kepler.dao.Storage;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Filtre anti-insulte (« bobba ») — remplace les mots interdits (table wordfilter) dans le chat.
 * Liste chargée depuis la base au premier usage (puis en cache). Mot entier, insensible à la casse/accents.
 */
public class WordfilterManager {
    private static volatile List<Object[]> compiled = null; // [Pattern, replacement]

    public static void reload() {
        List<Object[]> list = new ArrayList<>();
        Connection conn = null;
        PreparedStatement ps = null;
        ResultSet rs = null;
        try {
            conn = Storage.getStorage().getConnection();
            ps = Storage.getStorage().prepare("SELECT word, replacement FROM wordfilter WHERE enabled = 1", conn);
            rs = ps.executeQuery();
            while (rs.next()) {
                String word = rs.getString("word");
                String replacement = rs.getString("replacement");
                if (word == null || word.trim().isEmpty()) {
                    continue;
                }
                if (replacement == null || replacement.isEmpty()) {
                    replacement = "bobba";
                }
                Pattern pattern = Pattern.compile("(?iuU)\\b" + Pattern.quote(word.trim()) + "\\b");
                list.add(new Object[]{pattern, replacement});
            }
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(rs);
            Storage.closeSilently(ps);
            Storage.closeSilently(conn);
        }
        compiled = list;
    }

    public static String filterSentence(String message) {
        if (message == null || message.isEmpty()) {
            return message;
        }
        List<Object[]> list = compiled;
        if (list == null) {
            reload();
            list = compiled;
        }
        if (list == null || list.isEmpty()) {
            return message;
        }
        String out = message;
        for (Object[] entry : list) {
            Pattern pattern = (Pattern) entry[0];
            out = pattern.matcher(out).replaceAll(Matcher.quoteReplacement((String) entry[1]));
        }
        return out;
    }
}
