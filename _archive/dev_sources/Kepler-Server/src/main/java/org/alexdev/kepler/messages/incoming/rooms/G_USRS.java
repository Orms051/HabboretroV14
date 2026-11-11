package org.alexdev.kepler.messages.incoming.rooms;

import org.alexdev.kepler.game.entity.Entity;
import org.alexdev.kepler.game.player.Player;
import org.alexdev.kepler.game.room.Room;
import org.alexdev.kepler.messages.outgoing.rooms.user.USER_OBJECTS;
import org.alexdev.kepler.messages.types.MessageEvent;
import org.alexdev.kepler.server.netty.streams.NettyRequest;

import java.util.ArrayList;
import java.util.List;

public class G_USRS implements MessageEvent {
    @Override
    public void handle(Player player, NettyRequest reader) {
        if (player.getRoomUser().getRoom() == null) {
            return;
        }

        if (player.getRoomUser().getGamePlayer() != null && player.getRoomUser().getGamePlayer().isInGame()) {
            return; // Not needed for game arenas
        }

        Room room = player.getRoomUser().getRoom();

        // Exclude invisible (Silent) staff the entering player is not allowed to see.
        List<Entity> visible = new ArrayList<>();

        for (Entity entity : room.getEntities()) {
            if (Room.canSeeHidden(player, entity)) {
                visible.add(entity);
            }
        }

        player.send(new USER_OBJECTS(visible));
    }
}
