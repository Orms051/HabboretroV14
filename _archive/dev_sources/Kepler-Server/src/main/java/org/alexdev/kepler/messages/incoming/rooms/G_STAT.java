package org.alexdev.kepler.messages.incoming.rooms;

import org.alexdev.kepler.game.entity.Entity;
import org.alexdev.kepler.game.games.Game;
import org.alexdev.kepler.game.item.Item;
import org.alexdev.kepler.game.item.base.ItemBehaviour;
import org.alexdev.kepler.game.player.Player;
import org.alexdev.kepler.game.room.Room;
import org.alexdev.kepler.messages.outgoing.games.GAMESTART;
import org.alexdev.kepler.messages.outgoing.rooms.items.DICE_VALUE;
import org.alexdev.kepler.messages.outgoing.rooms.items.SHOWPROGRAM;
import org.alexdev.kepler.messages.outgoing.rooms.items.STUFFDATAUPDATE;
import org.alexdev.kepler.messages.outgoing.rooms.user.USER_OBJECTS;
import org.alexdev.kepler.messages.outgoing.rooms.user.USER_STATUSES;
import org.alexdev.kepler.messages.outgoing.rooms.user.YOUARESPECTATOR;
import org.alexdev.kepler.messages.types.MessageEvent;
import org.alexdev.kepler.server.netty.streams.NettyRequest;

import java.util.ArrayList;
import java.util.List;

public class G_STAT implements MessageEvent {
    @Override
    public void handle(Player player, NettyRequest reader) {
        if (player.getRoomUser().getRoom() == null) {
            return;
        }

        if (player.getRoomUser().getGamePlayer() != null && player.getRoomUser().getGamePlayer().isSpectator()) {
            player.send(new YOUARESPECTATOR());

            Game game = player.getRoomUser().getGamePlayer().getGame();

            if (game.isGameStarted()) {
                player.send(new GAMESTART(game.getTotalSecondsLeft().get()));
            }
            return;
        }

        if (player.getRoomUser().getGamePlayer() != null && player.getRoomUser().getGamePlayer().isInGame()) {
            return; // Not needed for game arenas
        }

        Room room = player.getRoomUser().getRoom();

        // Only refresh rights when in private room
        if (!room.isPublicRoom()) {
            room.refreshRights(player);
        }

        // Broadcast this player's arrival, but hide it from ordinary users if invisible.
        room.sendVisible(new USER_OBJECTS(player), player, List.of(player));

        // Build the list of entities this player is allowed to see (exclude hidden staff).
        List<Entity> visible = new ArrayList<>();

        for (Entity entity : room.getEntities()) {
            if (Room.canSeeHidden(player, entity)) {
                visible.add(entity);
            }
        }

        player.send(new USER_OBJECTS(visible));

        room.getEntityManager().tryRoomEntry(player);

        player.send(new USER_STATUSES(visible));
        player.getRoomUser().setNeedsUpdate(true);

        for (Item item : room.getItems()) {
            if (item.getCurrentProgramValue().length() > 0) {
                player.send(new SHOWPROGRAM(new String[] { item.getCurrentProgram(), item.getCurrentProgramValue() }));
            }

            // If item is requiring an update, apply animations etc
            if (item.getRequiresUpdate()) {
                // For some reason the wheel of fortune doesn't spin when the custom data on initial road equals -1, thus we send it again
                if (item.hasBehaviour(ItemBehaviour.WHEEL_OF_FORTUNE)) {
                    player.send(new STUFFDATAUPDATE(item));
                }

                // Dices use a separate packet for rolling animation
                if (item.hasBehaviour(ItemBehaviour.DICE)) {
                    player.send(new DICE_VALUE(item.getId(), true, 0));
                }
            }
        }
    }
}
