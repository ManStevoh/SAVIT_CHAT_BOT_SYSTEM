import Link from "next/link"
import useSWR from "swr"
import { LegalLayout } from "@/components/lando/legal-layout"
import { SeoHead, type SeoPayload } from "@/components/seo/SeoHead"
import { apiRequest } from "@/lib/api-client"

type BlogPost = {
  id: string
  title: string
  slug: string
  excerpt?: string | null
  body: string
  coverImage?: string | null
  publishedAt?: string | null
}

export default function BlogShowPage({
  slug,
  seo,
  initialPost,
}: {
  slug: string
  seo?: SeoPayload | null
  initialPost?: BlogPost | null
}) {
  const { data, isLoading, error } = useSWR<{ post: BlogPost }>(
    slug ? `/api/blog/posts/${slug}` : null,
    (url: string) => apiRequest<{ post: BlogPost }>(url),
    {
      fallbackData: initialPost ? { post: initialPost } : undefined,
      revalidateOnMount: !initialPost,
      revalidateOnFocus: false,
    }
  )

  const post = data?.post ?? initialPost ?? undefined
  const showLoading = isLoading && !post

  return (
    <>
      <SeoHead seo={seo} fallbackTitle={post?.title || "Blog — RelayIQ"} />
      <LegalLayout
        title={post?.title || "Blog"}
        activePath="/blog"
        plain={!post}
        intro={
          <p className="!mt-4">
            <Link href="/blog" className="text-sm font-medium text-primary hover:underline">
              ← All posts
            </Link>
          </p>
        }
      >
        {showLoading && <p className="text-sm text-muted-foreground">Loading…</p>}
        {error && !post && <p className="text-sm text-destructive">Post not found.</p>}

        {post && (
          <article>
            {post.publishedAt ? (
              <time className="block text-xs font-medium text-muted-foreground" dateTime={post.publishedAt}>
                {new Date(post.publishedAt).toLocaleDateString(undefined, {
                  year: "numeric",
                  month: "long",
                  day: "numeric",
                })}
              </time>
            ) : null}
            {post.excerpt ? (
              <p className="mt-3 text-lg leading-relaxed text-muted-foreground">{post.excerpt}</p>
            ) : null}
            {post.coverImage ? (
              <img
                src={post.coverImage}
                alt={post.title}
                loading="eager"
                decoding="async"
                className="mt-6 aspect-[2/1] w-full rounded-2xl border border-border object-cover shadow-sm"
              />
            ) : null}
            <div
              className="prose prose-sm dark:prose-invert mt-8 max-w-none text-muted-foreground prose-headings:font-semibold prose-headings:text-foreground prose-a:text-primary"
              dangerouslySetInnerHTML={{ __html: post.body }}
            />
          </article>
        )}
      </LegalLayout>
    </>
  )
}
