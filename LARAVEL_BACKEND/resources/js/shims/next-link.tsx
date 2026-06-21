import { Link as InertiaLink, type InertiaLinkProps } from '@inertiajs/react'
import { forwardRef, type AnchorHTMLAttributes, type ReactNode } from 'react'

type NextLinkProps = Omit<InertiaLinkProps, 'href'> & {
  href: string
  children?: ReactNode
} & AnchorHTMLAttributes<HTMLAnchorElement>

const Link = forwardRef<HTMLAnchorElement, NextLinkProps>(function Link(
  { href, children, prefetch, ...props },
  ref,
) {
  return (
    <InertiaLink ref={ref} href={href} prefetch={prefetch} {...props}>
      {children}
    </InertiaLink>
  )
})

export default Link
