package org.alexdev.kepler.messages.outgoing.games;

import org.alexdev.kepler.game.games.snowstorm.SnowStormTurn;
import org.alexdev.kepler.messages.types.MessageComposer;
import org.alexdev.kepler.server.netty.streams.NettyResponse;

import java.util.List;

public class SNOWSTORM_GAMESTATUS extends MessageComposer {
    private final List<SnowStormTurn> turns;
    private final int turnNumber;
    private final int checksum;

    public SNOWSTORM_GAMESTATUS(List<SnowStormTurn> events, int turnNumber, int checksum) {
        this.turns = events;
        this.turnNumber = turnNumber;
        this.checksum = checksum;
    }

    @Override
    public void compose(NettyResponse response) {
        response.writeInt(this.turnNumber);
        response.writeInt(this.checksum);
        response.writeInt(this.turns.size() == 0 ? 1 : this.turns.size());

        for (var turn : this.turns) {
            response.writeInt(turn.getSubTurns().size());

            for (var gameObject : turn.getSubTurns()) {
                gameObject.serialiseObject(response);
            }
        }
    }

    @Override
    public short getHeader() {
        return 244; // "Cs"
    }
}
