/**
 * Admin layout state: the mobile navigation drawer.
 * Focus trap and focus restoration come from x-trap on the drawer panel.
 */
export function adminShell() {
    return {
        drawerOpen: false,

        openDrawer() {
            this.drawerOpen = true;
        },

        closeDrawer() {
            this.drawerOpen = false;
        },

        toggleDrawer() {
            this.drawerOpen = !this.drawerOpen;
        },
    };
}
