package org.alexdev.kepler.dao.mysql;

import org.alexdev.kepler.dao.Storage;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.util.ArrayList;
import java.util.List;

/**
 * Journal des mouvements de crédits (porte-monnaie / « Transactions du compte »).
 * Alimente le dialogue client hh_purse (paquet 209).
 */
public class CreditLogDao {
    private static boolean ensured = false;

    /** Crée la table au besoin (filet, au cas où la migration n'a pas été jouée). */
    private static void ensureTable(Connection conn) {
        if (ensured) {
            return;
        }

        PreparedStatement ps = null;

        try {
            ps = Storage.getStorage().prepare("CREATE TABLE IF NOT EXISTS credit_logs (" +
                    "id INT AUTO_INCREMENT PRIMARY KEY, " +
                    "user_id INT NOT NULL, " +
                    "credit_value INT NOT NULL DEFAULT 0, " +
                    "real_value VARCHAR(16) NOT NULL DEFAULT '0', " +
                    "currency VARCHAR(16) NOT NULL DEFAULT '', " +
                    "system_name VARCHAR(32) NOT NULL DEFAULT 'web_internal', " +
                    "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, " +
                    "INDEX ix_user (user_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", conn);
            ps.execute();
            ensured = true;
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(ps);
        }
    }

    /**
     * Enregistre un mouvement de crédits.
     *
     * @param userId      le joueur concerné
     * @param creditValue le delta signé (+ reçu, - dépensé)
     * @param realValue   la valeur en argent réel (ou "0")
     * @param currency    la devise (ou "")
     * @param systemName  la clé d'activité (ex. stuff_store, web_internal, club_habbo)
     */
    public static void add(int userId, int creditValue, String realValue, String currency, String systemName) {
        Connection conn = null;
        PreparedStatement ps = null;

        try {
            conn = Storage.getStorage().getConnection();
            ensureTable(conn);

            ps = Storage.getStorage().prepare("INSERT INTO credit_logs (user_id, credit_value, real_value, currency, system_name) VALUES (?, ?, ?, ?, ?)", conn);
            ps.setInt(1, userId);
            ps.setInt(2, creditValue);
            ps.setString(3, realValue == null ? "0" : realValue);
            ps.setString(4, currency == null ? "" : currency);
            ps.setString(5, (systemName == null || systemName.isEmpty()) ? "web_internal" : systemName);
            ps.execute();
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(ps);
            Storage.closeSilently(conn);
        }
    }

    /**
     * Récupère les derniers mouvements d'un joueur, du plus ancien au plus récent
     * (le client affiche ensuite le plus récent en haut).
     *
     * Chaque ligne : {date (dd-MM-yyyy), heure (HH:mm), credit_value, real_value, currency, system_name}.
     */
    public static List<String[]> getLog(int userId, int limit) {
        List<String[]> list = new ArrayList<>();

        Connection conn = null;
        PreparedStatement ps = null;
        ResultSet row = null;

        try {
            conn = Storage.getStorage().getConnection();
            ensureTable(conn);

            ps = Storage.getStorage().prepare("SELECT DATE_FORMAT(created_at,'%d-%m-%Y') AS d, DATE_FORMAT(created_at,'%H:%i') AS t, credit_value, real_value, currency, system_name " +
                    "FROM (SELECT * FROM credit_logs WHERE user_id = ? ORDER BY id DESC LIMIT " + (limit > 0 ? limit : 100) + ") x ORDER BY id ASC", conn);
            ps.setInt(1, userId);
            row = ps.executeQuery();

            while (row != null && row.next()) {
                list.add(new String[]{
                        row.getString("d"),
                        row.getString("t"),
                        Integer.toString(row.getInt("credit_value")),
                        row.getString("real_value"),
                        row.getString("currency"),
                        row.getString("system_name")
                });
            }
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(row);
            Storage.closeSilently(ps);
            Storage.closeSilently(conn);
        }

        return list;
    }
}
