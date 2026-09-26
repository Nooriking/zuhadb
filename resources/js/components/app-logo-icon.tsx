// resources/js/components/app-logo-icon.tsx

import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return <img src="/images/logo.png" alt="ZUHA PERFUME" className={props.className} style={{ ...props.style }} />;
}
