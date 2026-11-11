package org.alexdev.kepler.game.commands.registered;

import org.alexdev.kepler.game.commands.Command;
import org.alexdev.kepler.game.entity.Entity;
import org.alexdev.kepler.game.entity.EntityType;
import org.alexdev.kepler.game.fuserights.Fuseright;
import org.alexdev.kepler.game.player.Player;
import org.alexdev.kepler.game.room.Room;
import org.alexdev.kepler.game.room.entities.RoomEntity;
import org.alexdev.kepler.game.room.mapping.RoomTile;
import org.alexdev.kepler.game.pathfinder.Position;
import org.alexdev.kepler.messages.outgoing.user.ALERT;
import org.alexdev.kepler.messages.outgoing.rooms.user.LOGOUT;
import org.alexdev.kepler.messages.outgoing.rooms.user.USER_OBJECTS;
import org.alexdev.kepler.messages.outgoing.rooms.user.USER_STATUSES;

import java.util.List;

/**
 * Toggle the staff "Silent" (invisible) mode. The avatar disappears for ordinary users;
 * staff holding fuse_enter_invisible keep seeing each other. Server-side right is enforced
 * here (not only client-side).
 */
public class InvisibleCommand extends Command {
    @Override
    public void addPermissions() {
        this.permissions.add(Fuseright.ENTER_INVISIBLE);
    }

    @Override
    public void handleCommand(Entity entity, String message, String[] args) {
        if (entity.getType() != EntityType.PLAYER) {
            return;
        }

        Player player = (Player) entity;

        // Server-side safety: re-check the right even though the command map gates it.
        if (!player.hasFuse(Fuseright.ENTER_INVISIBLE)) {
            return;
        }

        RoomEntity roomUser = player.getRoomUser();
        Room room = roomUser.getRoom();

        if (room == null) {
            return;
        }

        boolean goingInvisible = !roomUser.isInvisible();

        // Re-sort the entity on its current tile so collision follows the new state
        // immediately (invisible -> non-blocking, visible -> blocking again).
        Position position = roomUser.getPosition();
        RoomTile tile = (position != null) ? room.getMapping().getTile(position) : null;

        if (tile != null) {
            tile.removeEntity(player);
        }

        roomUser.setInvisible(goingInvisible);

        if (tile != null) {
            tile.addEntity(player);
        }

        int instanceId = roomUser.getInstanceId();

        if (goingInvisible) {
            // Make the avatar vanish for ordinary users (staff with the right keep seeing it).
            for (Player other : room.getEntityManager().getPlayers()) {
                if (other == player) {
                    continue;
                }

                if (!other.hasFuse(Fuseright.ENTER_INVISIBLE)) {
                    other.send(new LOGOUT(instanceId));
                }
            }

            player.send(new ALERT("Mode invisible activé : les Habbos ordinaires ne te voient plus. Retape :invisible pour redevenir visible."));
        } else {
            // Make the avatar reappear for ordinary users.
            for (Player other : room.getEntityManager().getPlayers()) {
                if (other == player) {
                    continue;
                }

                if (!other.hasFuse(Fuseright.ENTER_INVISIBLE)) {
                    other.send(new USER_OBJECTS(player));
                    other.send(new USER_STATUSES(List.of(player)));
                }
            }

            player.send(new ALERT("Mode invisible désactivé : tu es de nouveau visible."));
        }

        // Keep the room visitor count in sync (invisible staff are not counted).
        room.getData().setVisitorsNow(room.getEntityManager().getVisiblePlayerCount());
    }

    @Override
    public String getDescription() {
        return "Active/désactive le mode invisible (staff)";
    }
}
