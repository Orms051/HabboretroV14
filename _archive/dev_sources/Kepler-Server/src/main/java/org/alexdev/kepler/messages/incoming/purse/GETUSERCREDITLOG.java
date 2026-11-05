package org.alexdev.kepler.messages.incoming.purse;

import org.alexdev.kepler.game.player.Player;
import org.alexdev.kepler.messages.outgoing.purse.USER_CREDIT_LOG;
import org.alexdev.kepler.messages.types.MessageEvent;
import org.alexdev.kepler.server.netty.streams.NettyRequest;

public class GETUSERCREDITLOG implements MessageEvent {

    @Override
    public void handle(Player player, NettyRequest reader) {
        // Répond le journal des transactions (dialogue « Transactions du compte »).
        player.send(new USER_CREDIT_LOG(player.getDetails().getId()));
    }
}
