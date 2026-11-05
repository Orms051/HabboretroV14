package org.alexdev.kepler.messages.outgoing.purse;

import org.alexdev.kepler.dao.mysql.CreditLogDao;
import org.alexdev.kepler.messages.types.MessageComposer;
import org.alexdev.kepler.server.netty.streams.NettyResponse;

import java.util.List;

/**
 * Journal des transactions du porte-monbo (dialogue « Transactions du compte »).
 * Lu par le cast client hh_purse (handle_purse, sujet 209) :
 *   lignes séparées par RETURN (\r), 6 champs séparés par TAB (\t) :
 *   date . heure . credit_value . real_value . currency . transaction_system_name
 * Un RETURN final est obligatoire (le client ignore la dernière ligne).
 */
public class USER_CREDIT_LOG extends MessageComposer {
    private final int userId;

    public USER_CREDIT_LOG(int userId) {
        this.userId = userId;
    }

    @Override
    public void compose(NettyResponse response) {
        List<String[]> rows = CreditLogDao.getLog(this.userId, 100);

        StringBuilder sb = new StringBuilder();

        for (String[] row : rows) {
            sb.append(row[0]).append('\t')   // date
              .append(row[1]).append('\t')   // heure
              .append(row[2]).append('\t')   // credit_value (colonne ACTIVITÉ)
              .append(row[3]).append('\t')   // real_value (colonne VALEUR)
              .append(row[4]).append('\t')   // currency
              .append(row[5])                // transaction_system_name (colonne DESCRIPTION)
              .append('\r');
        }

        response.write(sb.toString());
    }

    @Override
    public short getHeader() {
        return 209;
    }
}
