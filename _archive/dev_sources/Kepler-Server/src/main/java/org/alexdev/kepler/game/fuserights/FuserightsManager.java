package org.alexdev.kepler.game.fuserights;

import org.alexdev.kepler.dao.mysql.FuserightsDao;
import org.alexdev.kepler.game.player.PlayerDetails;
import org.alexdev.kepler.game.player.PlayerRank;

import java.util.*;

public class FuserightsManager {
    private static FuserightsManager instance;

    private List<Fuseright> fuserights;

    /**
     * Matrice de permissions configurable depuis l'admin : rang -> fuserights autorisés.
     * Chargée depuis la table rank_fuserights. Si elle est vide (table absente, vide
     * ou erreur SQL), on retombe automatiquement sur les valeurs d'origine (enum) :
     * aucun risque de verrouiller le staff.
     */
    private Map<Integer, Set<String>> rankFuserights;

    public FuserightsManager() {
        this.fuserights = new ArrayList<>();
        this.fuserights.addAll(Arrays.asList(Fuseright.values()));
        this.rankFuserights = new HashMap<>();
        this.load();
    }

    /**
     * (Re)charge la matrice de permissions depuis la base. Si la table est vide,
     * elle est pré-remplie depuis les valeurs d'origine (enum). Toute erreur laisse
     * la matrice vide -> repli automatique sur l'enum.
     */
    public void load() {
        try {
            FuserightsDao.ensureTable();
            Map<Integer, Set<String>> map = FuserightsDao.getRankFuserights();

            if (map.isEmpty()) {
                FuserightsDao.seedFromEnum();
                map = FuserightsDao.getRankFuserights();
            }

            this.rankFuserights = (map != null) ? map : new HashMap<>();
        } catch (Exception e) {
            this.rankFuserights = new HashMap<>();
        }
    }

    /** Recharge la matrice à chaud (ex. après modification depuis l'admin). */
    public void reload() {
        this.load();
    }

    /** Vrai si la matrice configurable est utilisable ; sinon on utilise l'enum. */
    private boolean useDatabase() {
        return this.rankFuserights != null && !this.rankFuserights.isEmpty();
    }

    /**
     * Get the available fuserights for user.
     *
     * @param minimumRank the minimum rank to see the fuseright
     * @return the list of fuserights
     */
    public List<Fuseright> getFuserightsForRank(PlayerRank minimumRank) {
        List<Fuseright> fuses = new ArrayList<>();

        if (useDatabase()) {
            Set<String> allowed = this.rankFuserights.getOrDefault(minimumRank.getRankId(), Collections.emptySet());

            for (Fuseright f : this.fuserights) {
                // Les droits club sont désormais accordables par GRADE via la matrice admin
                // (en plus de l'abonnement HC). On ne saute que les droits de base sans rang mini.
                if (f.getMinimumRank() == null && !f.isClubOnly()) {
                    continue;
                }

                if (allowed.contains(f.getFuseright())) {
                    fuses.add(f);
                }
            }

            return fuses;
        }

        // Repli : comportement d'origine (enum, cumulatif)
        for (Fuseright f : this.fuserights) {
            if (f.getMinimumRank() == null || f.isClubOnly()) {
                continue;
            }

            if (minimumRank.getRankId() >= f.getMinimumRank().getRankId()) {
                fuses.add(f);
            }
        }

        return fuses;
    }

    /*
     * Get available fuserights for users with a club subscription
     */
    public List<Fuseright> getClubFuserights() {
        List<Fuseright> fuses = new ArrayList<>();

        for (Fuseright f : this.fuserights) {
            if (f.isClubOnly()) {
                fuses.add(f);
            }
        }

        return fuses;
    }

    /**
     * Get if the rank has a fuseright.
     *
     * @param fuse the fuse to check against
     * @return true, if successful
     */
    public boolean hasFuseright(Fuseright fuse, PlayerDetails details) {
        if (useDatabase()) {
            Set<String> allowed = this.rankFuserights.getOrDefault(details.getRank().getRankId(), Collections.emptySet());

            // Accordé par grade via la matrice : droits de modération ET avantages club.
            if (allowed.contains(fuse.getFuseright())) {
                return true;
            }

            if (details.hasClubSubscription() && fuse.isClubOnly()) {
                return true;
            }

            return false;
        }

        // Repli : comportement d'origine (enum)
        for (Fuseright f : this.fuserights) {
            if (f.getMinimumRank() == null) {
                continue;
            }

            if (details.getRank().getRankId() >= f.getMinimumRank().getRankId() && f == fuse) {
                return true;
            }
        }

        if (details.hasClubSubscription()) {
            for (Fuseright f : this.fuserights) {
                if (f.isClubOnly() && f == fuse) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the fuseright manager instance.
     *
     * @return the fuseright manager
     */
    public static FuserightsManager getInstance() {
        if (instance == null) {
            instance = new FuserightsManager();
        }

        return instance;
    }
}
