package org.alexdev.kepler.dao.mysql;

import org.alexdev.kepler.dao.Storage;
import org.alexdev.kepler.game.moderation.cfh.CallForHelp;
import org.alexdev.kepler.game.room.Room;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;

/**
 * Persistance des Appels à l'aide (Call For Help) vers la table calls_for_help.
 *
 * Kepler garde la file CFH en mémoire (CallForHelpManager) et la diffuse aux
 * modérateurs connectés en jeu : ce DAO ne change RIEN à ce comportement live,
 * il ne fait qu'enregistrer chaque appel en base pour que l'admin web puisse
 * afficher la file, l'historique et le compteur du bouton d'alerte.
 *
 * Toutes les méthodes avalent leurs erreurs (Storage.logError) : si la table
 * n'existe pas ou que la base est indisponible, le jeu continue normalement.
 */
public class CallForHelpDao {

    /**
     * Enregistre un nouvel appel (statut « open ») et pose l'id généré sur l'objet.
     *
     * @param cfh l'appel qui vient d'être soumis
     */
    public static void create(CallForHelp cfh) {
        Connection sqlConnection = null;
        PreparedStatement preparedStatement = null;
        ResultSet generatedKeys = null;

        try {
            sqlConnection = Storage.getStorage().getConnection();
            preparedStatement = Storage.getStorage().prepare(
                    "INSERT INTO calls_for_help (caller_id, room_id, room_name, message, category, status) VALUES (?, ?, ?, ?, ?, 'open')",
                    sqlConnection);

            Room room = cfh.getRoom();
            String roomName = (room != null && room.getData() != null) ? room.getData().getName() : "";
            String message = cfh.getMessage() != null ? cfh.getMessage() : "";

            preparedStatement.setInt(1, cfh.getCaller());
            preparedStatement.setInt(2, room != null ? room.getId() : 0);
            preparedStatement.setString(3, truncate(roomName, 255));
            preparedStatement.setString(4, truncate(message, 1000));
            preparedStatement.setInt(5, cfh.getCategory());
            preparedStatement.executeUpdate();

            generatedKeys = preparedStatement.getGeneratedKeys();
            if (generatedKeys != null && generatedKeys.next()) {
                cfh.setDbId(generatedKeys.getInt(1));
            }
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(generatedKeys);
            Storage.closeSilently(preparedStatement);
            Storage.closeSilently(sqlConnection);
        }
    }

    /**
     * Marque l'appel comme pris en charge par un modérateur.
     *
     * @param dbId        id de la ligne calls_for_help
     * @param moderatorId id du modérateur
     */
    public static void markPickedUp(int dbId, int moderatorId) {
        if (dbId <= 0) {
            return;
        }

        Connection sqlConnection = null;
        PreparedStatement preparedStatement = null;

        try {
            sqlConnection = Storage.getStorage().getConnection();
            preparedStatement = Storage.getStorage().prepare(
                    "UPDATE calls_for_help SET status='picked', picked_up_by=?, picked_at=NOW() WHERE id=? AND status<>'closed'",
                    sqlConnection);
            preparedStatement.setInt(1, moderatorId);
            preparedStatement.setInt(2, dbId);
            preparedStatement.execute();
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(preparedStatement);
            Storage.closeSilently(sqlConnection);
        }
    }

    /**
     * Met à jour la catégorie d'un appel.
     *
     * @param dbId     id de la ligne calls_for_help
     * @param category nouvelle catégorie
     */
    public static void updateCategory(int dbId, int category) {
        if (dbId <= 0) {
            return;
        }

        Connection sqlConnection = null;
        PreparedStatement preparedStatement = null;

        try {
            sqlConnection = Storage.getStorage().getConnection();
            preparedStatement = Storage.getStorage().prepare(
                    "UPDATE calls_for_help SET category=? WHERE id=?",
                    sqlConnection);
            preparedStatement.setInt(1, category);
            preparedStatement.setInt(2, dbId);
            preparedStatement.execute();
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(preparedStatement);
            Storage.closeSilently(sqlConnection);
        }
    }

    /**
     * Clôture un appel (résolu / supprimé en jeu).
     *
     * @param dbId id de la ligne calls_for_help
     */
    public static void markClosed(int dbId) {
        if (dbId <= 0) {
            return;
        }

        Connection sqlConnection = null;
        PreparedStatement preparedStatement = null;

        try {
            sqlConnection = Storage.getStorage().getConnection();
            preparedStatement = Storage.getStorage().prepare(
                    "UPDATE calls_for_help SET status='closed', closed_at=NOW() WHERE id=? AND status<>'closed'",
                    sqlConnection);
            preparedStatement.setInt(1, dbId);
            preparedStatement.execute();
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(preparedStatement);
            Storage.closeSilently(sqlConnection);
        }
    }

    /**
     * Clôt les appels restés ouverts d'une exécution précédente : au démarrage, la
     * file mémoire est vide, donc toute ligne « open »/« picked » est périmée. Garde
     * le compteur du bouton d'alerte honnête (pas de faux « en attente » fantômes).
     */
    public static void closeDangling() {
        Connection sqlConnection = null;
        PreparedStatement preparedStatement = null;

        try {
            sqlConnection = Storage.getStorage().getConnection();
            preparedStatement = Storage.getStorage().prepare(
                    "UPDATE calls_for_help SET status='closed', closed_at=NOW(), close_reason='Serveur redémarré' WHERE status IN ('open','picked')",
                    sqlConnection);
            preparedStatement.execute();
        } catch (Exception e) {
            Storage.logError(e);
        } finally {
            Storage.closeSilently(preparedStatement);
            Storage.closeSilently(sqlConnection);
        }
    }

    private static String truncate(String value, int max) {
        if (value == null) {
            return "";
        }
        return value.length() > max ? value.substring(0, max) : value;
    }
}
